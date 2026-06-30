<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Support\DepartmentScope;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    use ScopesByDepartment;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isOrgWide = DepartmentScope::isOrgWide($user);
        $dept = $user->department;

        $documentTypes = $this->scopedDocuments()->distinct()->orderBy('document_type')->pluck('document_type');
        $statuses = DocumentStatus::values();

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

        // ---- Reshape into the plain arrays the Civic Record analytics view expects ----
        $kpis = [
            'in_transit' => $summary['at_department'],
            'completed' => $summary['completed'],
            'submitted_month' => $summary['submitted_month'],
            'overdue' => $summary['overdue'],
        ];

        $byType = $byType->map(fn ($row) => [
            'label' => $row->document_type,
            'count' => (int) $row->total,
        ])->all();

        $topDepartments = $topDepartments->map(fn ($row) => [
            'name' => $row->name,
            'scans' => (int) $row->total,
        ])->all();

        $categories = $documentTypes->all();
        $activity = $this->buildActivitySeries($request);

        return view('analytics', compact(
            'documentTypes',
            'statuses',
            'topDepartments',
            'statusBreakdown',
            'byType',
            'summary',
            'kpis',
            'categories',
            'activity',
            'isOrgWide',
            'dept'
        ));
    }

    public function chartData(Request $request)
    {
        $request->validate([
            'document_type' => 'nullable|string',
            'status' => ['nullable', 'string', Rule::in(DocumentStatus::values())],
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

    /**
     * Server-rendered "submitted vs completed per day" series for the analytics
     * chart. Honours the toolbar filters (category, status, from, to); defaults
     * to the last 30 days.
     *
     * @return array<int, array{date: string, submitted: int, completed: int}>
     */
    private function buildActivitySeries(Request $request): array
    {
        $fromDate = Carbon::parse($request->filled('from') ? $request->from : now()->subDays(29)->toDateString())->startOfDay();
        $toDate = Carbon::parse($request->filled('to') ? $request->to : now()->toDateString())->endOfDay();

        $applyFilters = function ($query) use ($request) {
            if ($request->filled('category')) {
                $query->where('document_type', $request->category);
            }
            if ($request->filled('status') && in_array($request->status, DocumentStatus::values(), true)) {
                $query->where('status', $request->status);
            }

            return $query;
        };

        $created = $applyFilters($this->scopedDocuments())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        $completed = $applyFilters($this->scopedDocuments())
            ->whereNotNull('completed_at')
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('COUNT(*) as total'))
            ->whereBetween('completed_at', [$fromDate, $toDate])
            ->groupBy('date')
            ->pluck('total', 'date');

        $series = [];
        foreach (CarbonPeriod::create($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $series[] = [
                'date' => $day->format('M j'),
                'submitted' => (int) ($created[$key] ?? 0),
                'completed' => (int) ($completed[$key] ?? 0),
            ];
        }

        return $series;
    }

    private function buildSummary(): array
    {
        $atDeptNow = $this->scopeDocuments(
            Document::query()->whereIn('status', DocumentStatus::activeValues())
        )->count();

        $completed = $this->scopedDocuments(
            Document::query()->where('status', DocumentStatus::Completed->value)
        )->count();

        $submittedThisMonth = $this->scopedDocuments(
            Document::query()->where('created_at', '>=', now()->startOfMonth())
        )->count();

        $overdue = $this->scopeDocuments(Document::query())
            ->whereIn('status', DocumentStatus::activeValues())
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
