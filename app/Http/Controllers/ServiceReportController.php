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
        return view('reports.services', [
            'kpis' => $report->kpis(),
            'volumeByType' => $report->requestVolumeByType(),
            'bookingsByResource' => $report->bookingsByResource(),
            'requirementStats' => $report->requirementStats(),
        ]);
    }
}
