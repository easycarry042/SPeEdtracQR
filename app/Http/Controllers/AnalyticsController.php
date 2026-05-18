<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentScan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        $this->ensureCanViewReports();

        $documentTypes = Document::query()->distinct()->orderBy('document_type')->pluck('document_type');
        $statuses = ['pending', 'in_transit', 'completed', 'returned'];

        $topDepartments = DocumentScan::query()
            ->join('departments', 'document_scans.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('COUNT(document_scans.id) as total'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        return view('analytics', compact('documentTypes', 'statuses', 'topDepartments'));
    }

    public function chartData(Request $request)
    {
        $this->ensureCanViewReports();

        $request->validate([
            'document_type' => 'nullable|string',
            'status' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $query = Document::query();

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Group by date (last 30 days by default, or use period from filters)
        $fromDate = Carbon::parse($request->filled('from') ? $request->from : now()->subDays(30)->toDateString())->startOfDay();
        $toDate = Carbon::parse($request->filled('to') ? $request->to : now()->toDateString())->endOfDay();

        $data = $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as accepted"),
                DB::raw("SUM(CASE WHEN status IN ('pending','in_transit','rejected') THEN 1 ELSE 0 END) as pending_rejected")
            )
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $grouped = $data->keyBy('date');
        $period = CarbonPeriod::create($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay());

        $labels = [];
        $accepted = [];
        $pendingRejected = [];
        $total = [];

        foreach ($period as $date) {
            $dateKey = $date->toDateString();
            $row = $grouped->get($dateKey);

            $labels[] = $dateKey;
            $accepted[] = (int) ($row->accepted ?? 0);
            $pendingRejected[] = (int) ($row->pending_rejected ?? 0);
            $total[] = (int) ($row->total ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'accepted' => $accepted,
            'pending_rejected' => $pendingRejected,
            'total' => $total,
        ]);
    }

    private function ensureCanViewReports(): void
    {
        // Allow any authenticated user to open analytics.
        // Route middleware already enforces authentication.
        if (! auth()->check()) {
            abort(403, 'You are not allowed to view analytics.');
        }
    }
}