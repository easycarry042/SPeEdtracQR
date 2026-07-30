<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\RouteTemplate;
use App\Notifications\DocumentEvent;
use App\Services\QrCodeService;
use App\Support\UploadRules;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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

        $templates = RouteTemplate::active()->with('steps.department')->orderBy('name')->get();

        // The wizard previews the endorsement chain client-side as the route
        // changes, so ship each template's resolved steps as JSON.
        $templatesJson = $templates->map(fn (RouteTemplate $template): array => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'steps' => $template->stepsForAmount(null)->map(fn ($step): array => [
                'step_order' => $step->step_order,
                'action' => $step->action,
                'department' => ['name' => $step->department->name, 'code' => $step->department->code],
            ])->values(),
        ])->values();

        return view('requests.create', [
            'department' => $department,
            'templates' => $templates,
            'templatesJson' => $templatesJson,
        ]);
    }

    public function store(Request $request)
    {
        $department = $this->requireDepartment();

        $validated = $request->validate([
            'route_template_id' => ['required', 'exists:route_templates,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'paper_scan' => UploadRules::rules(),
            // Optional supervisor-chosen QR placement on the scanned page.
            'qr_x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qr_y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'qr_size' => ['nullable', 'numeric', 'min:0.12', 'max:0.40'],
        ]);

        $template = RouteTemplate::active()->with('steps.department')->findOrFail($validated['route_template_id']);

        // The substance (amount, specifications) lives on the scanned paper; the
        // route drives the chain directly, so no amount branching here.
        $steps = $template->stepsForAmount(null);
        if ($steps->isEmpty()) {
            return back()->withInput()->withErrors([
                'route_template_id' => 'This route has no applicable steps. Check the template configuration.',
            ]);
        }

        // Internal dept-to-dept requests carry an INT- prefix so they read
        // distinctly from citizen tickets (SPD-).
        $trackingNumber = $this->qrCodeService->generateTrackingNumber('INT');

        $document = DB::transaction(function () use ($validated, $steps, $template, $department, $trackingNumber) {
            $document = Document::create([
                'tracking_number' => $trackingNumber,
                'document_type' => $template->name,
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

            $forwardSteps = $steps->values()->map(fn ($step): array => [
                'step_order' => $step->step_order,
                'department_id' => $step->department_id,
                'action' => $step->action,
                'status' => RequestStep::STATUS_PENDING,
                'started_at' => null,
            ])->all();

            $document->requestSteps()->createMany([$ownEndorsement, ...$forwardSteps]);

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

        // Keep the original paper scan, and (for raster images) archive a
        // digital copy with the QR stamped on — both as private attachments.
        if ($request->hasFile('paper_scan')) {
            $attachments = $this->storeAttachmentsForDocument($document, [$request->file('paper_scan')]);

            if ($qrResult['success'] && $attachments !== []) {
                $position = isset($validated['qr_x'], $validated['qr_y'])
                    ? ['x' => (float) $validated['qr_x'], 'y' => (float) $validated['qr_y']]
                    : null;

                $stampedPath = $this->qrCodeService->stampQrOntoImage(
                    $attachments[0]->file_path,
                    $qrResult['relative_path'],
                    $trackingNumber,
                    $position,
                    isset($validated['qr_size']) ? (float) $validated['qr_size'] : null,
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

        $document->logSystemComment(sprintf(
            'Internal request filed by %s (%s) via the %s route.',
            auth()->user()->name,
            $department->name,
            $template->name,
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

        return view('requests.show', [
            'document' => $document,
            'canAct' => $document->canActOnCurrentStep(auth()->user()),
            'currentStep' => $document->currentRequestStep(),
            'hasCustody' => $document->currentStepHasCustody(),
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
