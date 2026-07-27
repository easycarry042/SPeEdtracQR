<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Support\AdminAnalytics;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    /** Selectable look-back windows (days) for the command center. */
    private const array RANGES = [7, 30, 90];

    public function dashboard(Request $request): Factory|View
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

        $from = Date::now()->subDays($range - 1)->startOfDay();
        $to = Date::now()->endOfDay();

        $analytics = new AdminAnalytics($from, $to, $documentType);

        $filters = [
            'range' => $range,
            'document_type' => $documentType,
            'active' => $documentType || $range !== 30,
        ];

        return view('admin.dashboard', [
            'filters' => $filters,
            'documentTypes' => $documentTypes,
            'updatedAt' => Date::now(),
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

    /**
     * Export the current command-center analytics as a CSV the client's staff
     * can open in Excel. Honours the same range / document-type filters as the
     * dashboard. Export-only by design — the numbers mirror live records, so
     * there is no round-trip import.
     */
    public function export(Request $request): StreamedResponse
    {
        $documentTypes = Document::query()
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

        $from = Date::now()->subDays($range - 1)->startOfDay();
        $to = Date::now()->endOfDay();
        $analytics = new AdminAnalytics($from, $to, $documentType);

        $kpis = $analytics->kpis();
        $filename = 'analytics-'.Date::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($analytics, $kpis, $range, $documentType, $from, $to): void {
            $out = fopen('php://output', 'w');

            $write = fn (array $row) => fputcsv($out, $row);

            $write(['SPeED TraQR — Analytics export']);
            $write(['Generated', Date::now()->toDayDateTimeString()]);
            $write(['Window', $range.' days', $from->toDateString().' to '.$to->toDateString()]);
            $write(['Document type', $documentType ?: 'All']);
            $write([]);

            $write(['Key figures', 'Value']);
            $write(['Requests received', $kpis['total']['value'] ?? 0]);
            $write(['Completed', $kpis['completed']['value'] ?? 0]);
            $write(['Active', $kpis['active']['value'] ?? 0]);
            $write(['At risk', $kpis['at_risk']['value'] ?? 0]);
            $write(['Avg processing (days)', $kpis['avg_processing']['value'] ?? 0]);
            $write([]);

            $write(['Status', 'Count']);
            foreach ($analytics->statusDistribution() as $row) {
                $write([$row['label'], $row['value']]);
            }
            $write([]);

            $write(['Document type', 'Count']);
            foreach ($analytics->typeBreakdown() as $row) {
                $write([$row['label'], $row['value']]);
            }
            $write([]);

            $write(['Staff member', 'Completed', 'Active']);
            foreach ($analytics->staffWorkload() as $row) {
                $write([$row['name'], $row['completed'], $row['active']]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
