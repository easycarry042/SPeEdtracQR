<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Support\AdminAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /** Selectable look-back windows (days) for the command center. */
    private const RANGES = [7, 30, 90];

    public function dashboard(Request $request)
    {
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $documentTypes = Document::query()
            ->select('document_type')
            ->whereNotNull('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        $validated = $request->validate([
            'range' => ['nullable', Rule::in(self::RANGES)],
            'department_id' => ['nullable', Rule::in($departments->pluck('id')->all())],
            'document_type' => ['nullable', Rule::in($documentTypes->all())],
        ]);

        $range = (int) ($validated['range'] ?? 30);
        $departmentId = $validated['department_id'] ?? null;
        $documentType = $validated['document_type'] ?? null;

        $from = Carbon::now()->subDays($range - 1)->startOfDay();
        $to = Carbon::now()->endOfDay();

        $analytics = new AdminAnalytics($from, $to, $departmentId ? (int) $departmentId : null, $documentType);

        $filters = [
            'range' => $range,
            'department_id' => $departmentId,
            'document_type' => $documentType,
            'active' => $departmentId || $documentType || $range !== 30,
        ];

        return view('admin.dashboard', [
            'filters' => $filters,
            'departments' => $departments,
            'documentTypes' => $documentTypes,
            'updatedAt' => Carbon::now(),
            'kpis' => $analytics->kpis(),
            'throughput' => $analytics->throughput(),
            'statusDistribution' => $analytics->statusDistribution(),
            'staffWorkload' => $analytics->staffWorkload(),
            'bottlenecks' => $analytics->bottlenecks(),
            'timeByStage' => $analytics->timeByStage(),
            'typeBreakdown' => $analytics->typeBreakdown(),
            'byDepartment' => $analytics->byDepartment(),
            'atRisk' => $analytics->atRisk(),
            'fastestStaff' => $analytics->fastestStaff(),
            'busiestDepartments' => $analytics->busiestDepartments(),
            'heatmap' => $analytics->throughputHeatmap(),
        ]);
    }
}
