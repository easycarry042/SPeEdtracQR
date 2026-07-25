<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\RequestReview;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Staff operational dashboard. Lists the requests assigned to the authenticated
 * staff member that are still being worked (In Progress / In Review) so they can
 * review and complete them.
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

        $requests = Document::with('attachments')
            ->where('assigned_to', $user->id)
            ->whereIn('status', $activeStatuses)
            ->latest('assigned_at')
            ->get();

        return view('staff.dashboard', [
            'requests' => $requests,
            'requestPayload' => $requests->map(fn (Document $d): array => RequestReview::forModal($d))->values(),
            // The forward line, so the modal cockpit can render the stepper and
            // compute the next stage entirely client-side (no reload per advance).
            'flow' => collect(DocumentStatus::flow())
                ->map(fn ($s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values()->all(),
            'assignedCount' => $requests->count(),
            'completedCount' => Document::where('assigned_to', $user->id)
                ->where('status', DocumentStatus::Completed->value)
                ->count(),
        ]);
    }
}
