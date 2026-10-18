<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesToAssignedWork;
use App\Models\Document;
use App\Support\AssignmentScope;
use App\Support\CitizenThread;
use App\Support\CitizenThreadAccess;
use App\Support\CitizenView;
use App\Support\CompletionPredictor;
use App\Support\DocumentSeal;
use App\Support\RequestReview;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Activitylog\Models\Activity;

class TrackController extends Controller
{
    use ScopesToAssignedWork;

    /**
     * The QR / tracking-number hub: the way in when the paper is in hand.
     *
     * This used to serve the Look Up desk as well — a browsable table of the
     * work in scope with a detail modal per row. The desk was a second copy of
     * each dashboard's queue that could adjust a claiming date and answer the
     * citizen but could not accept an assignment, while the dashboards could
     * accept but neither of the other two. Staff had to cross between screens
     * to finish one request. The desk's panels moved into the Review modal on
     * the dashboards and the desk itself is gone; this hub is what remains.
     */
    public function index(Request $request)
    {
        $trackingNumber = trim((string) $request->get('tracking_number'));

        if ($trackingNumber !== '') {
            return to_route('track.show', $trackingNumber);
        }

        // ?open=SPD-… used to deep link a row open on the desk. The desk is
        // gone, so the request resolves on its own page instead of being
        // silently dropped on a screen that no longer lists it.
        $open = strtoupper(trim((string) $request->get('open')));

        if ($open !== '') {
            return to_route('track.show', $open);
        }

        // Guests search inline on the landing page — there is no standalone
        // public "Look up" page. Send them home (they still reach a result via a
        // tracking number or a scanned QR, which resolve on track.show).
        if (! auth()->check()) {
            return to_route('welcome');
        }

        return view('track.index');
    }

    /**
     * JSON lookup for the inline landing-page search. Validates the format
     * server-side, then reports one of three outcomes: invalid format,
     * no record found, or the found request's public status summary. Internal
     * dept-to-dept requests are never resolved here (public must not see them).
     */
    public function lookup(Request $request)
    {
        $tracking = strtoupper(trim((string) $request->get('tracking_number')));

        if ($tracking === '') {
            return response()->json([
                'status' => 'invalid',
                'title' => 'Invalid tracking number.',
                'message' => 'Please enter a tracking number.',
            ], 422);
        }

        // Real numbers are PREFIX-YYYYMMDD-XXXXXX (SPD = citizen, INT = internal).
        // Anything else — random characters, symbols, wrong shape — is invalid.
        if (! preg_match('/^(SPD|INT)-\d{8}-[0-9A-Z]{6}$/', $tracking)) {
            return response()->json([
                'status' => 'invalid',
                'title' => 'Invalid tracking number.',
                'message' => 'Please enter a valid tracking number using the correct format (e.g. SPD-20260728-K7M9Q2).',
            ], 422);
        }

        $document = Document::query()
            ->where('tracking_number', $tracking)
            ->where('origin', '!=', Document::ORIGIN_INTERNAL)
            ->with('department')
            ->first();

        if (! $document) {
            return response()->json([
                'status' => 'not_found',
                'title' => 'No record found.',
                'message' => "We couldn't find a request with that tracking number. Please check your tracking number and try again.",
            ], 404);
        }

        return response()->json([
            'status' => 'found',
            'data' => [
                'tracking_number' => $document->tracking_number,
                'document_type' => $document->document_type,
                'status' => $document->statusEnum()->value,
                'status_label' => $document->statusEnum()->label(),
                'department' => $document->department?->name,
                'submitted_at' => $document->created_at?->format('M j, Y'),
                'updated_at' => $document->updated_at?->diffForHumans(),
                'url' => $document->citizenTrackingUrl(),
            ],
        ]);
    }

    public function show($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)
            ->with('attachments')
            ->first();

        // Citizen-facing links (receipt, public lookup, citizen emails) ask for
        // ?view=citizen. Without it, a staff member signed in on the same
        // browser — a shared counter PC — was taken to the staff desk or the
        // admin dashboard instead of the citizen's tracking page.
        $citizenView = CitizenView::requested();

        // Soft-fail: a typo'd or unknown number lands back on a lookup page with
        // a clear message instead of a bare 404. Citizens get the public Track
        // page, which reports "not found" itself — the staff Look up hub sends
        // guests on to the landing page, losing the message.
        if (! $document) {
            if ($citizenView) {
                return to_route('citizen.track', ['tracking' => $trackingNumber]);
            }

            return to_route('track.index', ['find' => 1])
                ->withErrors(['lookup' => "No document found for \"{$trackingNumber}\". Check the number and try again."]);
        }

        // Internal dept-to-dept requests are handled in their own module, with a
        // different (endorsement-chain) workflow — never the citizen assign/advance
        // panel. Authenticated staff go to the Internal request view; the public
        // must never see internal requests, so the citizen view 404s (no info leak).
        if ($document->isInternal()) {
            if (! $citizenView) {
                return to_route('requests.show', $document);
            }

            abort(404);
        }

        if (! $citizenView && auth()->user()->can('manage system')) {
            return to_route('admin.dashboard');
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

        if (! $citizenView) {
            $this->authorizeDocumentAccess($document);

            $user = auth()->user();
            if (AssignmentScope::canViewAll($user)) {
                // Supervisor: Pending (review + assign inline) and In Progress.
                $supervisorView = true;

                $pending = $this->scopeDocuments(
                    Document::where('origin', '!=', Document::ORIGIN_INTERNAL)
                        ->where('status', DocumentStatus::Pending->value)->whereNull('assigned_to')->latest('created_at')
                )->get(['id', 'tracking_number', 'document_type', 'status', 'created_at', 'citizen_name']);

                $inProgress = $this->scopeDocuments(
                    Document::with('assignedTo')
                        ->where('origin', '!=', Document::ORIGIN_INTERNAL)
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

        // Conversation. Staff load both threads; the citizen view loads ONLY the
        // citizen-visible one, so an internal note cannot reach that page even if
        // a future template forgot to filter.
        // Both feeds read oldest-first, the way a conversation does — and the way
        // the live listeners append new messages. `reorder()` is required: the
        // relation is declared newest-first, and an added orderBy would only be a
        // tie-breaker behind it.
        if (! $citizenView) {
            $document->load([
                'comments' => fn ($query) => $query->reorder()->oldest(),
                'comments.author',
                'comments.replies.author',
            ]);

            // Opening the request is what marks the citizen's messages read and
            // clears the ticket's unread badge.
            CommentController::markCitizenMessagesRead($document);
        }

        // The citizen page no longer carries the conversation: it lives in
        // Speedy's "Office messages" tab, behind the contact check, and is
        // marked read when that tab opens (CitizenThread::markRead). Opening
        // this page used to mark it read — for anyone holding the number.

        $user = $citizenView ? null : auth()->user();
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
            ->map(function ($activity): ?array {
                $to = data_get($activity->properties, 'attributes.status');
                if (! $to) {
                    return null;
                }

                return [
                    'event' => 'Updated to '.DocumentStatus::fromLoose($to)->label(),
                    'timestamp' => $activity->created_at?->format('M d, Y h:i A'),
                    'action' => 'in',
                ];
            })
            ->filter()
            ->values();

        // Self-hosted completion-time estimate, derived from real timestamps.
        $prediction = app(CompletionPredictor::class)->predict($document);
        $anomaly = null;

        // QR lifecycle: physical custody (staff) + authenticity seal (issued docs).
        $custody = $citizenView ? null : $document->currentCustody();
        $verifyUrl = null;
        $sealSvg = null;
        if ($document->statusEnum() === DocumentStatus::Completed) {
            $verifyUrl = DocumentSeal::url($document);
            // SVG backend — no GD needed, embeds inline.
            $sealSvg = base64_encode(
                (string) QrCode::format('svg')->size(120)->margin(0)->generate($verifyUrl)
            );
        }

        $isPublicView = $citizenView;
        $view = $isPublicView ? 'track.show-citizen' : 'track.show';
        $citizenVerified = $isPublicView && CitizenThreadAccess::isVerified(request(), $document);

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
            'custody' => $custody,
            'verifyUrl' => $verifyUrl,
            'sealSvg' => $sealSvg,
            // Citizen composer state: whether this visitor has confirmed a contact
            // detail on the request, and whether confirming is even possible.
            'citizenVerified' => $citizenVerified,
            'citizenUnread' => $citizenVerified ? CitizenThread::unreadCount($document) : 0,
            'citizenCanVerify' => CitizenThreadAccess::canBeVerified($document),
        ]);
    }

    public function status($trackingNumber)
    {
        $document = Document::with('assignedTo')->where('tracking_number', $trackingNumber)->firstOrFail();

        return response()->json([
            'status' => $document->status,
            // "Handled by" value for the citizen page. In the manual model this
            // is the assigned staff member (null → "Not yet assigned").
            'current_department' => $document->assignedTo?->name,
            'updated_at' => $document->updated_at?->toISOString(),
        ]);
    }
}
