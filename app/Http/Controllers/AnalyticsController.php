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

    /**
     * Submitted vs completed per day, as JSON, for the caller's own documents.
     * The Analytics page that drew it is now Reports (App\Support\CommandCenter);
     * /analytics redirects there.
     */
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
}
