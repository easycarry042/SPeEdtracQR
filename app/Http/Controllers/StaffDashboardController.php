<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\RequestReview;

/**
 * Staff operational dashboard. Lists the requests assigned to the authenticated
 * staff member that are still being worked (In Progress / In Review) so they can
 * review and complete them.
 */
class StaffDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Active assigned work: everything past Pending and not yet finished.
        $activeStatuses = [
            DocumentStatus::InProgress->value,
            DocumentStatus::InReview->value,
            DocumentStatus::Approved->value,
        ];

        $requests = Document::with('attachments')
            ->where('assigned_to', $user->id)
            ->whereIn('status', $activeStatuses)
            ->latest('assigned_at')
            ->get();

        return view('staff.dashboard', [
            'requests' => $requests,
            'requestPayload' => $requests->map(fn ($d) => RequestReview::forModal($d))->values(),
            'assignedCount' => $requests->count(),
            'completedCount' => Document::where('assigned_to', $user->id)
                ->where('status', DocumentStatus::Completed->value)
                ->count(),
        ]);
    }
}
