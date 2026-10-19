<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Notifications\DocumentEvent;
use App\Services\QrCodeService;
use App\Support\InternalInbox;
use App\Support\InternalRequestForm;
use App\Support\UploadRules;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Supervisor-only wizard for internal dept-to-dept requests (e.g. procurement):
 * enter the details, attach the scanned paper request, pick a route template,
 * and get a QR-stamped chain of endorsement steps. External citizen ticket
 * creation (DocumentWebController) is untouched by this flow.
 */
class InternalRequestController extends Controller
{
    use StoresDocumentAttachments;

    public function __construct(private QrCodeService $qrCodeService) {}

    /**
     * Per-office inbox of internal requests. Department supervisors see what
     * awaits their office and what their office filed; org-wide admins see
     * everything. Guarded here (not by route middleware) because two different
     * permissions may enter.
     */
    public function index(Request $request): Factory|View
    {
        $user = auth()->user();
        abort_unless(
            $user->can('act on internal requests')
                || $user->can('create internal requests')
                || $user->can('manage system'),
            403,
        );

        return view('requests.index', InternalInbox::for($user, (string) $request->get('q')) + [
            // Drives the "+" action in the page bar: without a department there
            // is no office to file on behalf of, so the control is withheld.
            'canFile' => $user->can('create internal requests') && $user->department !== null,
        ]);
    }

    public function create(): Factory|View
    {
        $department = $this->requireDepartment();

        return view('requests.create', ['department' => $department] + InternalRequestForm::data());
    }

    public function store(Request $request)
    {
        $department = $this->requireDepartment();

        $validated = $request->validate([
            'first_department_id' => ['required', 'exists:departments,id'],
            'purpose' => ['required', 'string', 'max:255'],
            // Is there paper that will be carried to the first office? Without
            // it there is no folder to scan in, so the filer names who receives
            // the request there.
            // Absent means paper, as every request was before the choice existed.
            'has_hard_copy' => ['sometimes', 'boolean'],
            'recipient_id' => [
                Rule::requiredIf(fn (): bool => $request->has('has_hard_copy') && ! $request->boolean('has_hard_copy')),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value && ! InternalRequestForm::isRecipientAt((int) $value, (int) $request->input('first_department_id'))) {
                        $fail('Choose someone who works in the first office.');
                    }
                },
            ],
            // The signed paper is the request: filing without the scan leaves
            // the receiving office nothing to act on, so it is required.
            'paper_scan' => UploadRules::rules(required: true),
            // Optional supervisor-chosen QR placement on the scanned page.
            'qr_x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qr_y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qr_size' => ['nullable', 'numeric', 'min:0.12', 'max:0.40'],
            // Which page of a multi-page PDF scan carries the stamp.
            'qr_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], [
            'recipient_id.required' => 'Without a hard copy, choose who in the first office receives this request.',
        ]);

        $hasHardCopy = (bool) ($validated['has_hard_copy'] ?? true);
        $recipientId = $hasHardCopy ? null : (int) $validated['recipient_id'];

        $firstDepartment = Department::active()->findOrFail($validated['first_department_id']);

        // Internal dept-to-dept requests carry an INT- prefix so they read
        // distinctly from citizen tickets (SPD-).
        $trackingNumber = $this->qrCodeService->generateTrackingNumber('INT');

        $document = DB::transaction(function () use ($validated, $firstDepartment, $department, $trackingNumber, $hasHardCopy, $recipientId) {
            $document = Document::create([
                'tracking_number' => $trackingNumber,
                'document_type' => 'Internal Request',
                'purpose' => $validated['purpose'],
                'status' => DocumentStatus::Pending->value,
                'status_changed_at' => now(),
                'source' => 'walk_in',
                'origin' => Document::ORIGIN_INTERNAL,
                'has_hard_copy' => $hasHardCopy,
                'requesting_department_id' => $department->id,
                'created_by' => auth()->id(),
            ]);

            // Filing is the requesting office's sign-off, so the request goes
            // straight to the department the filer chose: that hop is current
            // from the start. Further offices are added by whoever handles it
            // there, rather than presumed here. (step_order 0 was the filing
            // office's own endorsement hop, which older requests may still have.)
            $document->requestSteps()->create([
                'step_order' => 1,
                'department_id' => $firstDepartment->id,
                'recipient_id' => $recipientId,
                'action' => 'Review and action',
                'status' => RequestStep::STATUS_CURRENT,
                'started_at' => now(),
            ]);

            // The filing office physically holds the paper it just created; the
            // trail starts with it. The receiving office still scans the folder
            // to take custody before acting (custody is per department). A soft
            // copy has no paper, so no trail.
            if ($hasHardCopy) {
                $document->custodyEvents()->create([
                    'user_id' => auth()->id(),
                    'capture_method' => 'manual',
                    'override_reason' => 'Filing office holds the paper at submission.',
                ]);
            }

            return $document;
        });

        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber);
        if ($qrResult['success']) {
            $document->update(['qr_code_path' => $qrResult['relative_path']]);
        }

        // Keep the original paper scan, and archive a digital copy with the QR
        // stamped on — both as private attachments.
        if ($request->hasFile('paper_scan')) {
            $attachments = $this->storeAttachmentsForDocument($document, [$request->file('paper_scan')]);

            if ($qrResult['success'] && $attachments !== []) {
                $original = $attachments[0];
                $position = isset($validated['qr_x'], $validated['qr_y'])
                    ? ['x' => (float) $validated['qr_x'], 'y' => (float) $validated['qr_y']]
                    : null;
                $sizeFraction = isset($validated['qr_size']) ? (float) $validated['qr_size'] : null;

                if ($this->isPdf($original->file_path)) {
                    // PHP has no PDF toolchain here, so a PDF scan is stamped in
                    // the browser with pdf-lib on the confirmation screen (same
                    // approach as the built-in PDF editor). Hand it the placement.
                    session()->flash('internal_pdf_stamp', [
                        'attachment_id' => $original->id,
                        'x' => $position['x'] ?? null,
                        'y' => $position['y'] ?? null,
                        'size' => $sizeFraction,
                        'page' => (int) ($validated['qr_page'] ?? 1),
                    ]);
                } else {
                    $stampedPath = $this->qrCodeService->stampQrOntoImage(
                        $original->file_path,
                        $qrResult['relative_path'],
                        $trackingNumber,
                        $position,
                        $sizeFraction,
                    );

                    if ($stampedPath !== null) {
                        $document->attachments()->create([
                            'file_path' => $stampedPath,
                            'uploaded_by' => auth()->id(),
                            'sort_order' => (int) $document->attachments()->max('sort_order') + 1,
                        ]);
                    }
                }
            }
        }

        $firstStep = $document->requestSteps()->with('recipient')->first();

        $document->logSystemComment(sprintf(
            'Internal request filed by %s (%s), routed first to %s (%s)%s.',
            auth()->user()->name,
            $department->name,
            $firstDepartment->name,
            $firstDepartment->code,
            $hasHardCopy ? '' : ' — soft copy only, received by '.($firstStep?->recipient?->name ?? 'the office'),
        ));

        // Ping the first hop: its supervisors, and for a soft copy the person
        // named to receive it. A paper request is on its way to them; a soft
        // copy is already there.
        if ($firstStep) {
            Notification::send(
                DocumentEvent::hopRecipients($firstStep, auth()->id()),
                DocumentEvent::internalHopArrived($document, $firstStep->action),
            );
        }

        return to_route('requests.created', $document);
    }

    /**
     * Chain + audit view of one internal request, with the action panel for
     * the supervisor whose office currently holds it.
     */
    public function show(Request $request, Document $document): Factory|View
    {
        abort_unless(auth()->check(), 403);
        abort_unless($document->isInternal(), 404);

        $document->load(['requestSteps.department', 'requestSteps.actedBy', 'requestSteps.recipient', 'requestingDepartment', 'attachments', 'creator']);

        $currentStep = $document->currentRequestStep();
        $forwardDepartments = Department::active()
            ->when($currentStep, fn ($query) => $query->where('id', '!=', $currentStep->department_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // ?modal=1: just the detail, fetched into the modal on the Internal page.
        return view($request->boolean('modal') ? 'requests.detail-modal' : 'requests.show', [
            'document' => $document,
            'canAct' => $document->canActOnCurrentStep(auth()->user()),
            'currentStep' => $currentStep,
            'hasCustody' => $document->currentStepHasCustody(),
            // The office holding the paper decides where it goes next, unless a
            // hop was already queued when the request was filed.
            'queuedNextStep' => $document->requestSteps
                ->firstWhere('status', RequestStep::STATUS_PENDING),
            'forwardDepartments' => $forwardDepartments,
            // A soft copy is forwarded to a named person at the next office.
            'forwardRecipients' => $document->isSoftCopy()
                ? InternalRequestForm::recipients($forwardDepartments->pluck('id')->all())
                : collect(),
            // Who scanned this folder, and when: the endorsement view has to
            // answer "who had this paper?" without digging through the audit log.
            'custodyTrail' => $document->custodyEvents()
                ->with('user:id,name,department_id', 'user.department:id,name')
                ->latest()
                ->take(15)
                ->get(),
        ]);
    }

    /**
     * Who can be named to receive a soft copy at one office — fetched by the
     * filing form when that office is picked.
     */
    public function recipients(Department $department): JsonResponse
    {
        abort_unless($department->is_active, 404);

        return response()->json(InternalRequestForm::recipients([$department->id])->get($department->id, []));
    }

    public function created(Document $document): Factory|View
    {
        abort_unless(auth()->check(), 403);
        abort_unless($document->isInternal(), 404);

        $document->load(['requestSteps.department', 'requestSteps.recipient', 'requestingDepartment', 'attachments']);

        return view('requests.created', ['document' => $document]);
    }

    /**
     * Store the QR-stamped copy of a PDF paper scan, produced in the browser by
     * pdf-lib right after filing. The original upload is untouched: this saves a
     * second attachment, exactly as the image path does server-side.
     */
    public function storeStampedScan(Request $request, Document $document): JsonResponse
    {
        abort_unless($document->isInternal(), 404);
        // Only the office that filed it (or an org-wide admin) may add the
        // stamped copy — the same people who could have filed it in the first place.
        abort_unless(
            $document->created_by === auth()->id()
                || auth()->user()?->can('manage system')
                || ($document->requesting_department_id !== null
                    && $document->requesting_department_id === auth()->user()?->department_id),
            403,
        );

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:'.UploadRules::MAX_KILOBYTES],
        ]);

        $binary = (string) file_get_contents($request->file('pdf')->getRealPath());

        // mimes: trusts the client's extension, so confirm the bytes are a PDF.
        if (! str_starts_with($binary, '%PDF-')) {
            throw ValidationException::withMessages(['pdf' => 'That file is not a valid PDF.']);
        }

        $path = "document-attachments/{$document->tracking_number}-qr-stamped.pdf";

        // Idempotent: a reload of the confirmation screen must not pile up copies.
        $attachment = $document->attachments()->firstOrNew(['file_path' => $path]);
        Storage::disk('local')->put($path, $binary);

        if (! $attachment->exists) {
            $attachment->fill([
                'uploaded_by' => auth()->id(),
                'sort_order' => (int) $document->attachments()->max('sort_order') + 1,
            ])->save();
        }

        return response()->json([
            'url' => $attachment->authorizedUrl(),
        ]);
    }

    /** Whether a stored path is a PDF (stamped in the browser, not with GD). */
    private function isPdf(?string $path): bool
    {
        return strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Internal requests are filed on behalf of an office, so the supervisor
     * must belong to an active one; org-wide/unscoped accounts are bounced
     * back to the dashboard with an explanation.
     */
    private function requireDepartment(): Department
    {
        $department = auth()->user()?->department;

        if (! $department || ! $department->is_active) {
            throw new HttpResponseException(
                to_route('dashboard')->with(
                    'error',
                    'Your account has no active department, so you cannot file internal requests. Ask a system administrator to assign one.',
                ),
            );
        }

        return $department;
    }
}
