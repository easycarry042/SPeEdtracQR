<?php

namespace App\Http\Controllers;

use App\Support\CommandCenter;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A supervisor's Reports: their office's numbers — citizen and internal
 * requests on separate sides — on the same page the super admin reads for the
 * whole city (App\Support\CommandCenter). It replaced the separate Analytics
 * page and Services report, whose figures disagreed with the dashboard.
 *
 * A super admin's numbers are their command center, so they are sent there.
 */
class ReportsController extends Controller
{
    public function index(Request $request): Factory|View|RedirectResponse
    {
        if ($request->user()->can('manage system')) {
            return to_route('admin.dashboard', $request->only('view', 'range', 'document_type'));
        }

        // A supervisor who heads no particular office reads every office.
        return view('reports.index', CommandCenter::data($request, $request->user()->department));
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        if ($request->user()->can('manage system')) {
            return to_route('admin.dashboard.export', $request->only('view', 'range', 'document_type'));
        }

        return CommandCenter::export($request, $request->user()->department);
    }
}
