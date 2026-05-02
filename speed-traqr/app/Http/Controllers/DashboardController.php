<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Department;
use App\Models\DocumentScan;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRequests = Document::count();
        $pendingRequest = Document::whereIn('status', ['pending', 'in_transit', 'returned'])->count();
        $completed = Document::where('status', 'completed')->count();

        $recentActivity = Document::query()
            ->with('currentDepartment')
            ->latest('created_at')
            ->take(10)
            ->get();

        $recentScans = DocumentScan::query()
            ->with(['document', 'department', 'user'])
            ->latest('scanned_at')
            ->take(10)
            ->get();

        $statusSummary = Document::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', compact(
            'totalRequests',
            'pendingRequest',
            'completed',
            'recentActivity',
            'recentScans',
            'statusSummary'
        ));
    }
}