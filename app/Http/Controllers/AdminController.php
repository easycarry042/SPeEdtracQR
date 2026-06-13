<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Models\User;
use App\Support\PredictiveAnalytics;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalDocuments = Document::count();
        $totalStaff = User::role(['staff', 'receiving_staff', 'department_admin'])->count();
        $totalDepartments = Department::count();
        $pendingDocuments = Document::whereIn('status', ['pending', 'in_transit', 'returned'])->count();

        $recentActivity = Document::with(['creator', 'currentDepartment'])
            ->latest()
            ->take(10)
            ->get();

        $recentScans = DocumentScan::with(['document', 'department', 'user'])
            ->latest('scanned_at')
            ->take(5)
            ->get();

        // ── Predictive insights (org-wide: admin sees every department) ───────
        $analytics = new PredictiveAnalytics;

        $bottlenecks = $analytics->bottlenecks()
            ->reject(fn ($row) => $row['level'] === 'ok' && ($row['current_load'] ?? 0) === 0)
            ->take(5)
            ->values();

        $anomalies = Document::with('currentDepartment')
            ->where('status', 'in_transit')
            ->whereNotNull('current_department_id')
            ->get()
            ->map(function ($doc) use ($analytics) {
                $anomaly = $analytics->detectAnomaly($doc);
                if (! $anomaly) {
                    return null;
                }
                $doc->setAttribute('anomaly', $anomaly);

                return $doc;
            })
            ->filter()
            ->sortByDesc(fn ($doc) => $doc->anomaly['over_by_hours'])
            ->take(6)
            ->values();

        return view('admin.dashboard', compact(
            'totalDocuments',
            'totalStaff',
            'totalDepartments',
            'pendingDocuments',
            'recentActivity',
            'recentScans',
            'bottlenecks',
            'anomalies'
        ));
    }
}
