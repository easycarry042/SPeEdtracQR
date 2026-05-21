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
        $user       = auth()->user();
        $isOrgWide  = $user->hasRole('super_admin');
        $deptId     = $user->department_id;

        $docScope = function ($q) use ($isOrgWide, $deptId) {
            if (! $isOrgWide && $deptId) {
                $q->where(function ($inner) use ($deptId) {
                    $inner->where('current_department_id', $deptId)
                          ->orWhereHas('scans', fn ($s) => $s->where('department_id', $deptId));
                });
            }
        };

        $scanScope = function ($q) use ($isOrgWide, $deptId) {
            if (! $isOrgWide && $deptId) {
                $q->where('department_id', $deptId);
            }
        };

        $totalRequests  = Document::tap($docScope)->count();
        $pendingRequest = Document::tap($docScope)->whereIn('status', ['pending', 'in_transit', 'returned'])->count();
        $completed      = Document::tap($docScope)->where('status', 'completed')->count();

        $recentActivity = Document::with('currentDepartment')
            ->tap($docScope)
            ->latest('created_at')
            ->take(10)
            ->get();

        $recentScans = DocumentScan::with(['document', 'department', 'user'])
            ->tap($scanScope)
            ->latest('scanned_at')
            ->take(10)
            ->get();

        $statusSummary = Document::tap($docScope)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $inTransitQuery = Document::with(['currentDepartment', 'scans'])
            ->whereIn('status', ['in_transit', 'pending'])
            ->whereNotNull('current_department_id');

        if (! $isOrgWide && $deptId) {
            $inTransitQuery->where('current_department_id', $deptId);
        }

        $atRiskDocuments = $inTransitQuery->get()->filter(function ($doc) {
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
            $dept   = $doc->currentDepartment;
            $lastIn = $doc->scans
                ->where('action', 'in')
                ->where('department_id', $doc->current_department_id)
                ->sortByDesc('scanned_at')
                ->first();
            $elapsed  = $lastIn ? Carbon::parse($lastIn->scanned_at)->diffInHours(now()) : 0;
            $remaining = max(0, $dept->sla_hours - $elapsed);
            $doc->sla_elapsed_hours   = $elapsed;
            $doc->sla_remaining_hours = $remaining;
            $doc->sla_overdue         = $elapsed > $dept->sla_hours;
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