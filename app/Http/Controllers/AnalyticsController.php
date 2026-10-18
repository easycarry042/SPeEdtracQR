<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesToAssignedWork;
use App\Models\Document;
use App\Support\AssignmentScope;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    use ScopesToAssignedWork;

    public function index(Request $request)
    {
        // Super admins get the full analytics command center on their dashboard;
        // the standalone page would just duplicate it.
        if (auth()->user()?->can('manage system')) {
            return to_route('admin.dashboard');
        }

        $user = auth()->user();
        // Only a super admin is org-wide, and they were redirected above — so
        // everyone who reaches this page is scoped to their own office. This
        // used to ask canViewAll(), which is true for a department head, and
        // every figure below was then computed across every department.
        $isOrgWide = $user?->can('manage system') ?? false;
        $documentTypes = $this->scopedDocuments()->distinct()->orderBy('document_type')->pluck('document_type');
        $statuses = DocumentStatus::values();

        $summary = $this->buildSummary();

        // Every panel reads the same scoped query, so none of them can disagree
        // about whose work this page is describing. The staff ranking used to
        // be built from an unscoped Document::query(), which listed every
        // department's staff to any department head who opened the page.
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

        $topStaff = $this->scopedDocuments()
            ->leftJoin('users', 'documents.assigned_to', '=', 'users.id')
            ->select(DB::raw("COALESCE(users.name, 'Unassigned') as name"), DB::raw('COUNT(documents.id) as total'))
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->take(8)
            ->get();

        // ---- Reshape into the plain arrays the Civic Record analytics view expects ----
        $kpis = [
            'in_progress' => $summary['in_progress'],
            'completed' => $summary['completed'],
            'submitted_month' => $summary['submitted_month'],
            'overdue' => $summary['overdue'],
        ];

        $byType = $byType->map(fn ($row): array => [
            'label' => $row->document_type,
            'count' => (int) $row->total,
        ])->all();

        $topStaff = $topStaff->map(fn ($row): array => [
            'name' => $row->name,
            'assigned' => (int) $row->total,
        ])->all();

        $categories = $documentTypes->all();
        $activity = $this->buildActivitySeries($request);

        return view('analytics', ['documentTypes' => $documentTypes, 'statuses' => $statuses, 'topStaff' => $topStaff, 'statusBreakdown' => $statusBreakdown, 'byType' => $byType, 'summary' => $summary, 'kpis' => $kpis, 'categories' => $categories, 'activity' => $activity, 'isOrgWide' => $isOrgWide]);
    }

    public function chartData(Request $request)
    {
        $request->validate([
            'document_type' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(DocumentStatus::values())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $fromDate = Date::parse($request->filled('from') ? $request->from : now()->subDays(30)->toDateString())->startOfDay();
        $toDate = Date::parse($request->filled('to') ? $request->to : now()->toDateString())->endOfDay();

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
            'scoped' => ! AssignmentScope::canViewAll(),
        ]);
    }

    private function scopedDocuments(): Builder
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
        $fromDate = Date::parse($request->filled('from') ? $request->from : now()->subDays(29)->toDateString())->startOfDay();
        $toDate = Date::parse($request->filled('to') ? $request->to : now()->toDateString())->endOfDay();

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
        $inProgressNow = $this->scopeDocuments(
            Document::query()->whereIn('status', DocumentStatus::activeValues())
        )->count();

        $completed = $this->scopeDocuments(
            Document::query()->where('status', DocumentStatus::Completed->value)
        )->count();

        $submittedThisMonth = $this->scopeDocuments(
            Document::query()->where('created_at', '>=', now()->startOfMonth())
        )->count();

        $overdue = $this->scopeDocuments(Document::query())
            ->whereIn('status', DocumentStatus::activeValues())
            ->get()
            ->filter(fn ($doc) => $doc->isOverdue())
            ->count();

        return [
            'in_progress' => $inProgressNow,
            'completed' => $completed,
            'submitted_month' => $submittedThisMonth,
            'overdue' => $overdue,
        ];
    }
}
