<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The numbers page, for two audiences: the super admin's command center (the
 * whole city) and a supervisor's Reports (one office). Same filters, same
 * panels, same CSV — the office page is the city page with a department
 * slice, so the two can never describe the same request differently.
 *
 * It replaced, for supervisors, a separate Analytics page and Services report
 * whose figures disagreed with their own dashboard (they counted every
 * office's internal requests as theirs).
 */
final class CommandCenter
{
    /** Selectable look-back windows (days). */
    public const array RANGES = [7, 30, 90];

    /**
     * The two sides. Citizen requests and office-to-office requests live
     * different lives — stage clocks versus endorsement hops — so each is read
     * on its own, never added together.
     */
    public const array VIEWS = ['citizen', 'internal'];

    /**
     * Everything the page renders.
     *
     * @param  Department|null  $office  one office (a supervisor's Reports); null = the city
     * @return array<string, mixed>
     */
    public static function data(Request $request, ?Department $office): array
    {
        $departmentId = $office?->id;
        [$view, $range, $documentType, $from, $to] = self::filters($request, $departmentId);

        $shared = [
            'audience' => $office ? 'office' : 'admin',
            'office' => $office,
            'filters' => [
                'view' => $view,
                'range' => $range,
                'document_type' => $documentType,
                'active' => $documentType || $range !== 30,
            ],
            'documentTypes' => self::citizenDocumentTypes($departmentId),
            'updatedAt' => Date::now(),
        ];

        if ($view === 'internal') {
            $internal = new InternalAnalytics($from, $to, $departmentId);

            return $shared + [
                'internalKpis' => $internal->kpis(),
                'internalWaiting' => $internal->waiting(),
                'waitingByOffice' => $internal->waitingByOffice(),
                'officeTurnaround' => $internal->officeTurnaround(),
                'byRequestingOffice' => $internal->byRequestingOffice(),
                'throughput' => $internal->throughput(),
                'recentActivity' => $internal->recentActivity(),
            ];
        }

        $analytics = new AdminAnalytics($from, $to, $documentType, $departmentId);
        $services = app(ServiceReport::class)->forDepartment($departmentId);

        return $shared + [
            'kpis' => $analytics->kpis(),
            // The city page compares offices and flags requests that reached
            // none; one office's page has nothing to compare.
            'departmentVolume' => $office ? collect() : $analytics->departmentVolume(),
            'unrouted' => $office ? collect() : $analytics->unrouted(),
            'recentActivity' => $analytics->recentActivity(),
            'throughput' => $analytics->throughput(),
            'statusDistribution' => $analytics->statusDistribution(),
            'staffWorkload' => $analytics->staffWorkload(),
            'timeByStage' => $analytics->timeByStage(),
            'typeBreakdown' => $analytics->typeBreakdown(),
            // From the retired Services report (all time).
            'requirementStats' => $services->requirementStats(),
            // An office's own bookable resources (the city's live on the
            // Resources page instead).
            'bookings' => $office ? $services->bookingsByResource() : collect(),
            'atRisk' => $analytics->atRisk(),
            // Held past their hold date: invisible to atRisk() because a hold
            // nulls the stage SLA, so they get their own panel.
            'stalledHolds' => $analytics->stalledHolds(),
        ];
    }

    /**
     * The current figures as a CSV the client's staff can open in Excel.
     * Honours the same side / range / document-type filters as the page.
     * Export-only by design — the numbers mirror live records.
     */
    public static function export(Request $request, ?Department $office): StreamedResponse
    {
        $departmentId = $office?->id;
        [$view, $range, $documentType, $from, $to] = self::filters($request, $departmentId);

        $filename = ($office ? str($office->code ?: 'office')->lower().'-' : '')
            .($view === 'internal' ? 'internal-requests-' : 'analytics-')
            .Date::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($view, $range, $documentType, $from, $to, $departmentId, $office): void {
            $out = fopen('php://output', 'w');
            $write = fn (array $row) => fputcsv($out, $row);

            $write(['SPeED TraQR — '.($view === 'internal' ? 'Internal requests export' : 'Citizen requests export')]);
            $write(['Office', $office?->name ?? 'All offices']);
            $write(['Generated', Date::now()->toDayDateTimeString()]);
            $write(['Window', $range.' days', $from->toDateString().' to '.$to->toDateString()]);

            $view === 'internal'
                ? self::writeInternalCsv($write, new InternalAnalytics($from, $to, $departmentId))
                : self::writeCitizenCsv($write, new AdminAnalytics($from, $to, $documentType, $departmentId), $documentType);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private static function writeCitizenCsv(callable $write, AdminAnalytics $analytics, ?string $documentType): void
    {
        $kpis = $analytics->kpis();

        $write(['Document type', $documentType ?: 'All']);
        $write([]);

        $write(['Key figures', 'Value']);
        $write(['Requests received', $kpis['total']['value'] ?? 0]);
        $write(['Completed', $kpis['completed']['value'] ?? 0]);
        $write(['Active', $kpis['active']['value'] ?? 0]);
        // The page's own words: past the stage's processing time, and close to it.
        $write(['Overdue', $kpis['overdue']['value'] ?? 0]);
        $write(['At risk (not yet overdue)', $kpis['at_risk']['value'] ?? 0]);
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
    }

    private static function writeInternalCsv(callable $write, InternalAnalytics $analytics): void
    {
        $kpis = $analytics->kpis();

        $write([]);
        $write(['Key figures', 'Value']);
        $write(['Requests filed', $kpis['filed']]);
        $write(['Moving through offices', $kpis['moving']]);
        $write(['Returned for revision', $kpis['returned']]);
        $write(['Completed', $kpis['completed']]);
        $write(['Denied', $kpis['denied']]);
        $write(['Overdue at a step (open now)', $kpis['overdue']]);
        $write(['Avg turnaround (days)', $kpis['avg_turnaround'] ?? '']);
        $write([]);

        $write(['Waiting at', 'Open', 'Overdue', 'Longest wait']);
        foreach ($analytics->waitingByOffice() as $row) {
            $write([$row['label'], $row['count'], $row['overdue'], $row['longest']]);
        }
        $write([]);

        $write(['Office', 'Hops closed', 'Average time to act', 'Turnaround']);
        foreach ($analytics->officeTurnaround() as $row) {
            $write([$row['label'], $row['hops'], $row['avg'], $row['limit']]);
        }
    }

    /**
     * @return array{0: string, 1: int, 2: ?string, 3: Carbon, 4: Carbon}
     */
    private static function filters(Request $request, ?int $departmentId): array
    {
        $validated = $request->validate([
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'range' => ['nullable', Rule::in(self::RANGES)],
            'document_type' => ['nullable', Rule::in(self::citizenDocumentTypes($departmentId)->all())],
        ]);

        $view = $validated['view'] ?? 'citizen';
        $range = (int) ($validated['range'] ?? 30);
        // Internal requests all share one type, so the slice is citizen-only.
        $documentType = $view === 'citizen' ? ($validated['document_type'] ?? null) : null;

        return [
            $view,
            $range,
            $documentType,
            Date::now()->subDays($range - 1)->startOfDay(),
            Date::now()->endOfDay(),
        ];
    }

    /** @return Collection<int, string> */
    private static function citizenDocumentTypes(?int $departmentId): Collection
    {
        return Document::query()
            ->citizenFacing()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->select('document_type')
            ->whereNotNull('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');
    }
}
