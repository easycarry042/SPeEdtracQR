<?php

namespace App\Http\Controllers;

use App\Support\CommandCenter;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The super admin's command center: the whole city's numbers. A supervisor's
 * Reports page (ReportsController) is the same page for one office — both are
 * built by App\Support\CommandCenter.
 */
class AdminController extends Controller
{
    public function dashboard(Request $request): Factory|View
    {
        return view('admin.dashboard', CommandCenter::data($request, office: null));
    }

    public function export(Request $request): StreamedResponse
    {
        return CommandCenter::export($request, office: null);
    }
}
