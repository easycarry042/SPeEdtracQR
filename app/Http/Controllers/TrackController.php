<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Support\AssignmentScope;
use App\Support\RequestReview;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class TrackController extends Controller
{
    use ScopesByDepartment;

    public function index(Request $request)
    {
        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        $trackingNumber = trim((string) $request->get('tracking_number'));

        if ($trackingNumber !== '') {
            return redirect()->route('track.show', $trackingNumber);
        }

        // Explicit finder mode (?find=1): render the Look up hub (tracking
        // search + QR image upload + live camera) instead of auto-opening a
        // work item. This is where /scan and "Open scanner" land.
        if ($request->boolean('find')) {
            return view('track.index');
        }

        // Supervisors land on the unified Track view (track.show) with the
        // Pending / In Progress split folded into its sidebar. Pick a sensible
        // default document to open: the first pending request, else the first
        // in-progress one. With nothing to show, fall back to the empty state.
        $user = auth()->user();
        if ($user && AssignmentScope::canViewAll($user)) {
            $default = $this->scopeDocuments(
                Document::where('status', DocumentStatus::Pending->value)->whereNull('assigned_to')->latest('created_at')
            )->first(['tracking_number']);

            $tab = 'pending';
            if (! $default) {
                $default = $this->scopeDocuments(
                    Document::whereNotNull('assigned_to')->where('status', '!=', DocumentStatus::Pending->value)->latest('updated_at')
                )->first(['tracking_number']);
                $tab = 'inprogress';
            }

            if ($default) {
                return redirect()->route('track.show', ['trackingNumber' => $default->tracking_number, 'tab' => $tab]);
            }

            return view('track.index');
        }

        // Staff land on the same sidebar+detail view: open the most recent
        // request assigned to them (In Progress tab), else their latest
        // completed one, else the empty search state.
        if ($user) {
            $active = Document::where('assigned_to', $user->id)
                ->whereIn('status', [
                    DocumentStatus::InProgress->value,
                    DocumentStatus::InReview->value,
                    DocumentStatus::Approved->value,
                ])
                ->latest('updated_at')
                ->first(['tracking_number']);

            if ($active) {
                return redirect()->route('track.show', ['trackingNumber' => $active->tracking_number, 'tab' => 'inprogress']);
            }

            $completed = Document::where('assigned_to', $user->id)
                ->where('status', DocumentStatus::Completed->value)
                ->latest('updated_at')
                ->first(['tracking_number']);

            if ($completed) {
                return redirect()->route('track.show', ['trackingNumber' => $completed->tracking_number, 'tab' => 'completed']);
            }

            // Fall back to anything in their scope so the page isn't empty.
            $latest = $this->scopeDocuments(
                Document::query()->whereIn('status', DocumentStatus::activeValues())->latest('created_at')
            )->first(['tracking_number']);

            if ($latest) {
                return redirect()->route('track.show', $latest->tracking_number);
            }
        }

        return view('track.index');
    }

    public function show($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)
            ->with('attachments')
            ->firstOrFail();

        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        $documents = collect();
        $supervisorView = false;
        $staffView = false;
        $pending = collect();
        $inProgress = collect();
        $myActive = collect();
        $myCompleted = collect();
        $assignableStaff = collect();
        $activeTab = in_array(request('tab'), ['pending', 'inprogress', 'completed'], true) ? request('tab') : 'inprogress';

        // Staff-active stages (assigned and still being worked).
        $staffActiveStatuses = [
            DocumentStatus::InProgress->value,
            DocumentStatus::InReview->value,
            DocumentStatus::Approved->value,
        ];

        if (auth()->check()) {
            $this->authorizeDocumentAccess($document);

            $user = auth()->user();
            if (AssignmentScope::canViewAll($user)) {
                // Supervisor: Pending (review + assign inline) and In Progress.
                $supervisorView = true;

                $pending = $this->scopeDocuments(
                    Document::where('status', DocumentStatus::Pending->value)->whereNull('assigned_to')->latest('created_at')
                )->get(['id', 'tracking_number', 'document_type', 'status', 'created_at', 'citizen_name']);

                $inProgress = $this->scopeDocuments(
                    Document::with('assignedTo')
                        ->whereNotNull('assigned_to')
                        ->where('status', '!=', DocumentStatus::Pending->value)
                        ->latest('updated_at')
                )->get();

                $assignableStaff = RequestReview::assignableStaff();
            } else {
                // Staff: same sidebar+detail design — In Progress (assigned to me,
                // active) and Completed (assigned to me, finished).
                $staffView = true;

                $myActive = Document::where('assigned_to', $user->id)
                    ->whereIn('status', $staffActiveStatuses)
                    ->latest('updated_at')
                    ->get(['id', 'tracking_number', 'document_type', 'status', 'created_at', 'citizen_name']);

                $myCompleted = Document::where('assigned_to', $user->id)
                    ->where('status', DocumentStatus::Completed->value)
                    ->latest('updated_at')
                    ->take(30)
                    ->get(['id', 'tracking_number', 'document_type', 'status', 'created_at', 'citizen_name']);
            }
        }

        // Collaboration feed. Staff see everything; the public view is filtered
        // to citizen-facing posts only (see the citizen Blade).
        $document->load(['comments.author', 'comments.replies.author']);

        $user = auth()->user();
        $canAct = false;
        if ($user && $document->status !== 'completed') {
            $canAct = AssignmentScope::canViewAll($user)
                || ((int) $document->assigned_to === (int) $user->id && $user->can('advance documents'));
        }

        $isLastStop = true;
        $nextDepartment = null;

        // Timeline of status-stage changes, reconstructed from the activity log
        // (manual model — no IN/OUT scans).
        $timeline = Activity::where('subject_type', $document->getMorphClass())
            ->where('subject_id', $document->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($activity) {
                $to = data_get($activity->properties, 'attributes.status');
                if (! $to) {
                    return null;
                }

                return [
                    'event' => 'Updated to '.DocumentStatus::fromLoose($to)->label(),
                    'timestamp' => optional($activity->created_at)->format('M d, Y h:i A'),
                    'action' => 'in',
                ];
            })
            ->filter()
            ->values();

        $prediction = null;
        $anomaly = null;

        $isPublicView = ! auth()->check();
        $view = $isPublicView ? 'track.show-citizen' : 'track.show';

        return view($view, [
            'document' => $document,
            'documents' => $documents,
            'supervisorView' => $supervisorView,
            'staffView' => $staffView,
            'pending' => $pending,
            'inProgress' => $inProgress,
            'myActive' => $myActive,
            'myCompleted' => $myCompleted,
            'assignableStaff' => $assignableStaff,
            'activeTab' => $activeTab,
            'routingChain' => collect(),
            'routingSteps' => collect(),
            'timeline' => $timeline,
            'canAct' => $canAct,
            'isLastStop' => $isLastStop,
            'nextDepartment' => $nextDepartment,
            'prediction' => $prediction,
            'anomaly' => $anomaly,
        ]);
    }

    public function status($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)->firstOrFail();

        return response()->json([
            'status' => $document->status,
            'current_department' => null,
            'updated_at' => $document->updated_at?->toISOString(),
        ]);
    }
}
