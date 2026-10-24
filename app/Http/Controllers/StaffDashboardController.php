<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\RequestReview;
use App\Support\SlaWatch;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Staff operational dashboard. Lists the requests assigned to the authenticated
 * staff member that are still being worked (In Progress / In Review) so they can
 * review and complete them.
 *
 * It also carries the at-risk list. That used to sit on the supervisor's
 * dashboard, which put the warning in front of the one person who could not
 * act on it: a supervisor can reassign, but only the assignee can advance the
 * request before its stage SLA runs out. Each staff member now sees their own.
 */
class StaffDashboardController extends Controller
{
    public function index(): Factory|View
    {
        $user = auth()->user();

        // Active assigned work, including newly assigned tickets awaiting acceptance
        // and items the staff member has parked (Returned / On Hold) — those stay
        // on the dashboard so they can be resumed from the same cockpit modal.
        $activeStatuses = [
            DocumentStatus::Pending->value,
            DocumentStatus::InProgress->value,
            DocumentStatus::InReview->value,
            DocumentStatus::Approved->value,
            DocumentStatus::Returned->value,
            DocumentStatus::OnHold->value,
        ];

        // Internal dept-to-dept requests travel their own endorsement chain on
        // the Internal Requests page, never this queue. The Look Up desk made
        // the same exclusion; this screen did not, which only went unnoticed
        // while the desk existed to be the clean one.
        $requests = Document::with('attachments', 'requirements')
            ->where('assigned_to', $user->id)
            ->where('origin', '!=', Document::ORIGIN_INTERNAL)
            ->whereIn('status', $activeStatuses)
            ->latest('assigned_at')
            ->get();

        // At risk = assigned work past 75% of its current stage's SLA budget.
        // Drawn from the set already loaded above, so the warning and the queue
        // under it can never disagree about what this staff member is holding.
        $atRisk = SlaWatch::atRisk($requests);

        return view('staff.dashboard', [
            'requests' => $requests,
            'atRisk' => $atRisk,
            'atRiskCount' => $atRisk->count(),
            // One payload drives the whole page: the queue table, the at-risk
            // rows above it, and the Review modal they both open.
            'requestPayload' => RequestReview::collection($requests),
            // The forward line, so the modal cockpit can render the stepper and
            // compute the next stage entirely client-side (no reload per advance).
            'flow' => collect(DocumentStatus::flow())
                ->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values()->all(),
            'assignedCount' => $requests->count(),
            // Headline counts for the three tiles. Pending is work not yet
            // picked up; everything else still open counts as in progress.
            'pendingCount' => $requests
                ->where('status', DocumentStatus::Pending->value)
                ->count(),
            'inProgressCount' => $requests
                ->whereNotIn('status', [DocumentStatus::Pending->value])
                ->count(),
            // Same set the Completed tab pages through (HistoryController::data).
            'completedCount' => Document::where('assigned_to', $user->id)
                ->citizenFacing()
                ->where('status', DocumentStatus::Completed->value)
                ->count(),
        ]);
    }
}
