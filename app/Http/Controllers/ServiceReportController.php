<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ServiceReport;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * Phase 3 — standing report on the citizen-facing services (request types and
 * resource bookings). Gated by the 'view reports' permission via the route.
 */
class ServiceReportController extends Controller
{
    public function index(ServiceReport $report): Factory|View
    {
        $user = auth()->user();

        // A department head reads their own office's services; a super admin
        // reads every office's. Passing null is what "org-wide" means here.
        $report = $report->forDepartment(
            $user?->can('manage system') ? null : $user?->department_id
        );

        return view('reports.services', [
            'kpis' => $report->kpis(),
            'volumeByType' => $report->requestVolumeByType(),
            'bookingsByResource' => $report->bookingsByResource(),
            'requirementStats' => $report->requirementStats(),
        ]);
    }
}
