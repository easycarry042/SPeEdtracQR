<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Notifications\DocumentEvent;
use App\Services\QrCodeService;
use App\Support\UploadRules;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

        $department = $user->department;
        $search = trim((string) $request->get('q'));

        $base = (fn () => Document::where('origin', Document::ORIGIN_INTERNAL)
            ->with(['requestingDepartment', 'creator', 'requestSteps.department'])
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search): void {
                $qq->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            }))
            ->latest());

        $active = DocumentStatus::activeValues();
        $terminal = [DocumentStatus::Completed->value, DocumentStatus::Denied->value];

        if ($department) {
            $awaiting = $base()->whereIn('status', $active)
                ->whereHas('requestSteps', fn ($q) => $q
                    ->where('status', RequestStep::STATUS_CURRENT)
                    ->where('department_id', $department->id))
                ->get();

            $filed = $base()->whereIn('status', $active)
                ->where('requesting_department_id', $department->id)
                ->get();

            // Closed history stays office-scoped: filed here or passed through here.
            $closed = $base()->whereIn('status', $terminal)
                ->where(fn ($q) => $q
                    ->where('requesting_department_id', $department->id)
                    ->orWhereHas('requestSteps', fn ($qq) => $qq->where('department_id', $department->id)))
                ->get();
        } else {
            $awaiting = $base()->whereIn('status', $active)->get();
            $filed = collect();
            $closed = $base()->whereIn('status', $terminal)->get();
        }

        return view('requests.index', [
            'department' => $department,
            'awaiting' => $awaiting,
            'filed' => $filed,
            'closed' => $closed,
            'search' => $search,
            'canFile' => $user->can('create internal requests') && $department !== null,
        ]);
    }

    public function create(): Factory|View
    {
        $department = $this->requireDepartment();

        // The filer picks the office the paper goes to first; the chain is that
        // department behind their own endorsement, rather than a preset route.
        $departments = Department::active()->orderBy('name')->get(['id', 'name', 'code']);

        return view('requests.create', [
            'department' => $department,
            'departments' => $departments,
            'departmentsJson' => $departments->map(fn (Department $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $department = $this->requireDepartment();

        $validated = $request->validate([
            'first_department_id' => ['required', 'exists:departments,id'],
            'purpose' => ['required', 'string', 'max:255'],
            // The signed paper is the request: filing without the scan leaves
            // the receiving office nothing to act on, so it is required.
            'paper_scan' => UploadRules::rules(required: true),
            // Optional supervisor-chosen QR placement on the scanned page.
            'qr_x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qr_y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qr_size' => ['nullable', 'numeric', 'min:0.12', 'max:0.40'],
            // Which page of a multi-page PDF scan carries the stamp.
            'qr_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $firstDepartment = Department::active()->findOrFail($validated['first_department_id']);

        // Internal dept-to-dept requests carry an INT- prefix so they read
        // distinctly from citizen tickets (SPD-).
        $trackingNumber = $this->qrCodeService->generateTrackingNumber('INT');

        $document = DB::transaction(function () use ($validated, $firstDepartment, $department, $trackingNumber) {
            $document = Document::create([
                'tracking_number' => $trackingNumber,
                'document_type' => 'Internal Request',
                'purpose' => $validated['purpose'],
                'status' => DocumentStatus::Pending->value,
                'status_changed_at' => now(),
                'source' => 'walk_in',
                'origin' => Document::ORIGIN_INTERNAL,
                'requesting_department_id' => $department->id,
                'created_by' => auth()->id(),
            ]);

            // The endorsement chain always opens with the requesting department's
            // own head: a drafted request must be reviewed and endorsed by the
            // department supervisor before it is forwarded to the first downstream
            // office. This first hop sits current immediately; the route template's
            // offices open, in order, as each earlier hop is approved.
            $ownEndorsement = [
                'step_order' => 0,
                'department_id' => $department->id,
                'action' => 'Department endorsement',
                'status' => RequestStep::STATUS_CURRENT,
                'started_at' => now(),
            ];

            // One forward hop: the department the filer chose. Further offices
            // are added by whoever handles it there, rather than presumed here.
            $forwardStep = [
                'step_order' => 1,
                'department_id' => $firstDepartment->id,
                'action' => 'Review and action',
                'status' => RequestStep::STATUS_PENDING,
                'started_at' => null,
            ];

            $document->requestSteps()->createMany([$ownEndorsement, $forwardStep]);

            // The filing office physically holds the paper it just created, so
            // record custody for this first hop automatically — the supervisor
            // endorses without a redundant scan (later hops still require one).
            $document->custodyEvents()->create([
                'user_id' => auth()->id(),
                'capture_method' => 'manual',
                'override_reason' => 'Filing office holds the paper at submission.',
            ]);

            return $document;
        });

        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber, url("/track/{$trackingNumber}"));
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

        $document->logSystemComment(sprintf(
            'Internal request filed by %s (%s), routed first to %s (%s).',
            auth()->user()->name,
            $department->name,
            $firstDepartment->name,
            $firstDepartment->code,
        ));

        // Ping the first hop's supervisors: the paper is on its way to them.
        $firstStep = $document->requestSteps()->first();
        if ($firstStep) {
            Notification::send(
                DocumentEvent::departmentSupervisors($firstStep->department_id, auth()->id()),
                DocumentEvent::internalHopArrived($document, $firstStep->action),
            );
        }

        return to_route('requests.created', $document);
    }

    /**
     * Chain + audit view of one internal request, with the action panel for
     * the supervisor whose office currently holds it.
     */
    public function show(Document $document): Factory|View
    {
        abort_unless(auth()->check(), 403);
        abort_unless($document->isInternal(), 404);

        $document->load(['requestSteps.department', 'requestSteps.actedBy', 'requestingDepartment', 'attachments', 'creator']);

        $currentStep = $document->currentRequestStep();

        return view('requests.show', [
            'document' => $document,
            'canAct' => $document->canActOnCurrentStep(auth()->user()),
            'currentStep' => $currentStep,
            'hasCustody' => $document->currentStepHasCustody(),
            // The office holding the paper decides where it goes next, unless a
            // hop was already queued when the request was filed.
            'queuedNextStep' => $document->requestSteps
                ->firstWhere('status', RequestStep::STATUS_PENDING),
            'forwardDepartments' => Department::active()
                ->when($currentStep, fn ($query) => $query->where('id', '!=', $currentStep->department_id))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            // Who scanned this folder, and when: the endorsement view has to
            // answer "who had this paper?" without digging through the audit log.
            'custodyTrail' => $document->custodyEvents()
                ->with('user:id,name,department_id', 'user.department:id,name')
                ->latest()
                ->take(15)
                ->get(),
        ]);
    }

    public function created(Document $document): Factory|View
    {
        abort_unless(auth()->check(), 403);
        abort_unless($document->isInternal(), 404);

        $document->load(['requestSteps.department', 'requestingDepartment', 'attachments']);

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
