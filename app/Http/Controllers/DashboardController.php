<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Support\DepartmentScope;
use App\Support\PredictiveAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ScopesByDepartment;

    public function index()
    {
        $user = auth()->user();
        $isOrgWide = DepartmentScope::isOrgWide($user);
        $dept = $user->department;

        $totalRequests = $this->scopeDocuments(Document::query())->count();
        $pendingRequest = $this->scopeDocuments(Document::query())->whereIn('status', ['pending', 'in_transit', 'returned'])->count();
        $completed = $this->scopeDocuments(Document::query())->where('status', 'completed')->count();

        $recentActivity = $this->scopeDocuments(
            Document::with('currentDepartment')->latest('created_at')
        )->take(10)->get();

        $recentScans = $this->scopeScans(
            DocumentScan::with(['document', 'department', 'user'])->latest('scanned_at')
        )->take(10)->get();

        $statusSummary = $this->scopeDocuments(Document::query())
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $inTransitQuery = $this->scopeCurrentDocuments(
            Document::with(['currentDepartment', 'scans'])
                ->whereIn('status', ['in_transit', 'pending'])
                ->whereNotNull('current_department_id')
        );

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

        // ── Predictive insights (self-hosted PredictiveAnalytics engine) ──────
        $analytics = new PredictiveAnalytics;

        $scopeDeptIds = $isOrgWide
            ? null
            : array_values(array_filter([DepartmentScope::departmentId($user)]));

        $bottlenecks = $analytics->bottlenecks($scopeDeptIds ?: null)
            ->reject(fn ($row) => $row['level'] === 'ok' && ($row['current_load'] ?? 0) === 0)
            ->take(5)
            ->values();

        $anomalies = $this->scopeCurrentDocuments(
            Document::with('currentDepartment')
                ->where('status', 'in_transit')
                ->whereNotNull('current_department_id')
        )->get()
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

        return view('dashboard', compact(
            'totalRequests',
            'pendingRequest',
            'completed',
            'recentActivity',
            'recentScans',
            'statusSummary',
            'atRiskDocuments',
            'atRiskCount',
            'bottlenecks',
            'anomalies',
            'dept',
            'isOrgWide'
        ));
    }
}
