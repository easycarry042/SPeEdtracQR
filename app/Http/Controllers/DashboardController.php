<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Department;
use App\Models\DocumentScan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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

        // Documents currently in a department — check which are at risk (>=75% SLA used)
        $inTransitDocs = Document::with(['currentDepartment', 'scans'])
            ->whereIn('status', ['in_transit', 'pending'])
            ->whereNotNull('current_department_id')
            ->get();

        $atRiskDocuments = $inTransitDocs->filter(function ($doc) {
            $dept = $doc->currentDepartment;
            if (! $dept || ! $dept->sla_hours) {
                return false;
            }
            $lastIn = $doc->scans
                ->where('action', 'in')
                ->where('department_id', $doc->current_department_id)
                ->sortByDesc('scanned_at')
                ->first();
            if (! $lastIn) {
                return false;
            }
            $elapsed = Carbon::parse($lastIn->scanned_at)->diffInHours(now());
            return ($elapsed / $dept->sla_hours) >= 0.75;
        })->map(function ($doc) {
            $dept = $doc->currentDepartment;
            $lastIn = $doc->scans
                ->where('action', 'in')
                ->where('department_id', $doc->current_department_id)
                ->sortByDesc('scanned_at')
                ->first();
            $elapsed = $lastIn ? Carbon::parse($lastIn->scanned_at)->diffInHours(now()) : 0;
            $remaining = max(0, $dept->sla_hours - $elapsed);
            $doc->sla_elapsed_hours = $elapsed;
            $doc->sla_remaining_hours = $remaining;
            $doc->sla_overdue = $elapsed > $dept->sla_hours;
            return $doc;
        })->sortBy('sla_remaining_hours')->values();

        $atRiskCount = $atRiskDocuments->count();

        return view('dashboard', compact(
            'totalRequests',
            'pendingRequest',
            'completed',
            'recentActivity',
            'recentScans',
            'statusSummary',
            'atRiskDocuments',
            'atRiskCount'
        ));
    }
}