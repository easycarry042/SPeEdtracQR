<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Support\DepartmentScope;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ScopesByDepartment;

    public function index()
    {
        $user = auth()->user();
        $isOrgWide = DepartmentScope::isOrgWide($user);
        $dept = $user->department;

        $documentTypes = $this->scopedDocuments()->distinct()->orderBy('document_type')->pluck('document_type');
        $statuses = ['pending', 'in_transit', 'completed'];

        $summary = $this->buildSummary();

        $topDepartments = collect();
        $statusBreakdown = collect();
        $byType = collect();

        if ($isOrgWide) {
            $topDepartments = DocumentScan::query()
                ->join('departments', 'document_scans.department_id', '=', 'departments.id')
                ->select('departments.name', DB::raw('COUNT(document_scans.id) as total'))
                ->groupBy('departments.id', 'departments.name')
                ->orderByDesc('total')
                ->take(8)
                ->get();

            $statusBreakdown = Document::query()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            $byType = Document::query()
                ->select('document_type', DB::raw('COUNT(*) as total'))
                ->groupBy('document_type')
                ->orderByDesc('total')
                ->take(6)
                ->get();
        } else {
            $statusBreakdown = $this->scopedDocuments()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            $byType = $this->scopedDocuments()
                ->select('document_type', DB::raw('COUNT(*) as total'))
                ->groupBy('document_type')
                ->orderByDesc('total')
                ->take(6)
                ->get();
        }

        return view('analytics', compact(
            'documentTypes',
            'statuses',
            'topDepartments',
            'statusBreakdown',
            'byType',
            'summary',
            'isOrgWide',
            'dept'
        ));
    }

    public function chartData(Request $request)
    {
        $request->validate([
            'document_type' => 'nullable|string',
            'status' => 'nullable|string|in:pending,in_transit,completed',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $fromDate = Carbon::parse($request->filled('from') ? $request->from : now()->subDays(30)->toDateString())->startOfDay();
        $toDate = Carbon::parse($request->filled('to') ? $request->to : now()->toDateString())->endOfDay();

        $query = $this->scopedDocuments();

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $created = (clone $query)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        $completed = (clone $query)
            ->whereNotNull('completed_at')
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('COUNT(*) as total'))
            ->whereBetween('completed_at', [$fromDate, $toDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        $period = CarbonPeriod::create($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay());

        $labels = [];
        $submitted = [];
        $completedSeries = [];
        foreach ($period as $date) {
            $dateKey = $date->toDateString();
            $labels[] = $dateKey;
            $submitted[] = (int) ($created[$dateKey] ?? 0);
            $completedSeries[] = (int) ($completed[$dateKey] ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'submitted' => $submitted,
            'completed' => $completedSeries,
            'scoped' => ! DepartmentScope::isOrgWide(),
        ]);
    }

    private function scopedDocuments()
    {
        return $this->scopeDocuments(Document::query());
    }

    private function buildSummary(): array
    {
        $atDeptNow = $this->scopeCurrentDocuments(
            Document::query()->whereIn('status', ['pending', 'in_transit'])
        )->count();

        $completed = $this->scopedDocuments(
            Document::query()->where('status', 'completed')
        )->count();

        $submittedThisMonth = $this->scopedDocuments(
            Document::query()->where('created_at', '>=', now()->startOfMonth())
        )->count();

        $overdue = $this->scopeCurrentDocuments(Document::query())
            ->whereIn('status', ['pending', 'in_transit'])
            ->whereNotNull('current_department_id')
            ->get()
            ->filter(fn ($doc) => $doc->isOverdue())
            ->count();

        return [
            'at_department' => $atDeptNow,
            'completed' => $completed,
            'submitted_month' => $submittedThisMonth,
            'overdue' => $overdue,
        ];
    }
}
