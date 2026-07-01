<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
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
        $documentTypes = Document::query()
            ->select('document_type')
            ->whereNotNull('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        $validated = $request->validate([
            'range' => ['nullable', Rule::in(self::RANGES)],
            'document_type' => ['nullable', Rule::in($documentTypes->all())],
        ]);

        $range = (int) ($validated['range'] ?? 30);
        $documentType = $validated['document_type'] ?? null;

        $from = Carbon::now()->subDays($range - 1)->startOfDay();
        $to = Carbon::now()->endOfDay();

        $analytics = new AdminAnalytics($from, $to, $documentType);

        $filters = [
            'range' => $range,
            'document_type' => $documentType,
            'active' => $documentType || $range !== 30,
        ];

        return view('admin.dashboard', [
            'filters' => $filters,
            'documentTypes' => $documentTypes,
            'updatedAt' => Carbon::now(),
            'kpis' => $analytics->kpis(),
            'throughput' => $analytics->throughput(),
            'statusDistribution' => $analytics->statusDistribution(),
            'staffWorkload' => $analytics->staffWorkload(),
            'bottlenecks' => $analytics->bottlenecks(),
            'timeByStage' => $analytics->timeByStage(),
            'typeBreakdown' => $analytics->typeBreakdown(),
            'atRisk' => $analytics->atRisk(),
            'fastestStaff' => $analytics->fastestStaff(),
            'heatmap' => $analytics->throughputHeatmap(),
            // Staff the admin can (re)assign an at-risk document to, from the panel.
            'assignableStaff' => User::permission('advance documents')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
