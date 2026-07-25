<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Activity;

/**
 * Org-wide read model for the super-admin analytics command center (Direction 1a).
 *
 * Every metric is sourced from the MANUAL status model — documents.status,
 * status_changed_at, completed_at, assigned_to — plus the Spatie activity log
 * for per-stage timing. Department has been removed from the system; analytics
 * are by-staff and by-document-type only.
 *
 * The document-type "slice" applies everywhere. Flow metrics (created/completed
 * throughput, type mix, KPIs) additionally honour the selected date window;
 * snapshot metrics (status distribution, active load, at-risk) describe "now".
 */
class AdminAnalytics
{
    /** Active load at/above this is flagged as overloaded in the workload chart. */
    private const int OVERLOADED_AT = 8;

    /** Elapsed share of a stage's SLA at which a document is "at risk" (not yet breached). */
    private const float AT_RISK_FRACTION = 0.75;

    private readonly Carbon $prevFrom;

    private readonly Carbon $prevTo;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly ?string $documentType = null,
    ) {
        // Previous window of equal length, ending just before $from, for deltas.
        $seconds = $this->from->diffInSeconds($this->to);
        $this->prevTo = $this->from->copy()->subSecond();
        $this->prevFrom = $this->prevTo->copy()->subSeconds($seconds);
    }

    // ── Region B: KPI vitals ─────────────────────────────────────────────────

    /** Six instant-read vitals: value + period delta + (where honest) a sparkline. */
    public function kpis(): array
    {
        $totalNow = $this->countCreated($this->from, $this->to);
        $totalPrev = $this->countCreated($this->prevFrom, $this->prevTo);

        $completedNow = $this->countCompleted($this->from, $this->to);
        $completedPrev = $this->countCompleted($this->prevFrom, $this->prevTo);

        $active = $this->scoped()->whereIn('status', DocumentStatus::activeValues())->count();
        $atRisk = $this->activeDocuments()->filter->isOverdue()->count();

        $avgNow = $this->avgProcessingDays($this->from, $this->to);
        $avgPrev = $this->avgProcessingDays($this->prevFrom, $this->prevTo);

        $onTimeNow = $this->onTimeRate($this->from, $this->to);
        $onTimePrev = $this->onTimeRate($this->prevFrom, $this->prevTo);

        return [
            'total' => [
                'value' => $totalNow,
                'delta' => $this->delta($totalNow, $totalPrev),
                'spark' => array_values($this->dailyCreated()),
            ],
            'completed' => [
                'value' => $completedNow,
                'delta' => $this->delta($completedNow, $completedPrev),
                'spark' => array_values($this->dailyCompleted()),
            ],
            'active' => [
                'value' => $active,
                'spark' => $this->dailyActive(),
            ],
            'at_risk' => [
                'value' => $atRisk,
                'pill' => $atRisk > 0 ? 'watch' : 'ok',
            ],
            'avg_processing' => [
                'value' => $avgNow,
                // Lower is better, so invert the delta direction for display.
                'delta' => $this->delta($avgNow ?? 0, $avgPrev ?? 0, lowerIsBetter: true),
            ],
            'on_time' => [
                'value' => $onTimeNow,
                'delta' => $this->delta($onTimeNow ?? 0, $onTimePrev ?? 0),
                'pill' => $onTimeNow === null ? 'muted' : ($onTimeNow >= 85 ? 'healthy' : ($onTimeNow >= 70 ? 'watch' : 'breach')),
            ],
        ];
    }

    // ── Region C: analytics grid ─────────────────────────────────────────────

    /** "Is the pipeline keeping up?" — created vs completed per week. */
    public function throughput(): array
    {
        $created = $this->bucketByWeek('created_at', $this->from, $this->to);
        $completed = $this->bucketByWeek('completed_at', $this->from, $this->to);

        $weeks = [];
        $cursor = $this->from->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $this->to->copy()->endOfWeek(Carbon::SATURDAY);
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $weeks[] = [
                'label' => $cursor->format('M j'),
                'created' => $created[$key] ?? 0,
                'completed' => $completed[$key] ?? 0,
            ];
            $cursor->addWeek();
        }

        return $weeks;
    }

    /** "Where do documents sit across stages?" — snapshot count per stage. */
    public function statusDistribution(): Collection
    {
        $counts = $this->scoped()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(DocumentStatus::cases())->map(fn (DocumentStatus $s): array => [
            'label' => $s->label(),
            'value' => (int) ($counts[$s->value] ?? 0),
            'band' => $s->band(),
        ]);
    }

    /** "Who is overloaded?" — completed (in window) + current active load per assignee. */
    public function staffWorkload(): Collection
    {
        $completed = $this->scoped()
            ->whereNotNull('assigned_to')
            ->whereBetween('completed_at', [$this->from, $this->to])
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $active = $this->scoped()
            ->whereNotNull('assigned_to')
            ->whereIn('status', DocumentStatus::activeValues())
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $ids = $completed->keys()->merge($active->keys())->unique();
        if ($ids->isEmpty()) {
            return collect();
        }

        $names = User::whereIn('id', $ids)->pluck('name', 'id');

        return $ids->map(fn ($id): array => [
            'name' => $names[$id] ?? 'Unknown',
            'completed' => (int) ($completed[$id] ?? 0),
            'active' => (int) ($active[$id] ?? 0),
            'overloaded' => (int) ($active[$id] ?? 0) >= self::OVERLOADED_AT,
        ])
            ->sortByDesc(fn ($r): float|int|array => $r['active'] + $r['completed'])
            ->take(8)
            ->values();
    }

    /** "What share is stuck / at-risk?" — split active docs + the worst stage. */
    public function bottlenecks(): array
    {
        $active = $this->activeDocuments();

        $onTime = 0;
        $atRisk = 0;
        $breached = 0;
        $breachedByStage = [];

        foreach ($active as $doc) {
            $stage = $doc->statusEnum();
            $sla = $stage->slaHours();
            $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;

            if (! $sla || ! $anchor) {
                $onTime++;

                continue;
            }

            $elapsed = abs($anchor->getTimestamp() - now()->getTimestamp()) / 3600;

            if ($elapsed > $sla) {
                $breached++;
                $breachedByStage[$stage->label()] = ($breachedByStage[$stage->label()] ?? 0) + 1;
            } elseif ($elapsed >= $sla * self::AT_RISK_FRACTION) {
                $atRisk++;
            } else {
                $onTime++;
            }
        }

        arsort($breachedByStage);

        return [
            'on_time' => $onTime,
            'at_risk' => $atRisk,
            'breached' => $breached,
            'total' => $active->count(),
            'worst_stage' => array_key_first($breachedByStage),
        ];
    }

    /**
     * "Which stage is slowest?" — average hours spent in each stage, reconstructed
     * from the activity log by diffing consecutive status-change timestamps. Only
     * CLOSED intervals (a stage the document has already left) are counted.
     */
    public function timeByStage(): Collection
    {
        $docs = $this->scoped()->get(['id', 'created_at', 'status']);
        if ($docs->isEmpty()) {
            return $this->emptyStageSeries();
        }

        $morph = (new Document)->getMorphClass();
        $activities = Activity::where('subject_type', $morph)
            ->whereIn('subject_id', $docs->pluck('id'))
            ->orderBy('created_at')
            ->get(['subject_id', 'properties', 'created_at'])
            ->groupBy('subject_id');

        $sum = [];   // stage value => total hours
        $count = []; // stage value => closed intervals

        foreach ($docs as $doc) {
            $prevTime = $doc->created_at;
            $prevStatus = null;

            foreach ($activities->get($doc->id, collect()) as $act) {
                $attrs = data_get($act->properties, 'attributes', []);
                if (! array_key_exists('status', $attrs)) {
                    continue; // not a status transition
                }

                $prevStatus ??= data_get($act->properties, 'old.status', DocumentStatus::Pending->value);

                if ($prevTime) {
                    $hours = abs($act->created_at->getTimestamp() - $prevTime->getTimestamp()) / 3600;
                    $sum[$prevStatus] = ($sum[$prevStatus] ?? 0) + $hours;
                    $count[$prevStatus] = ($count[$prevStatus] ?? 0) + 1;
                }

                $prevTime = $act->created_at;
                $prevStatus = $attrs['status'];
            }
        }

        return collect(DocumentStatus::cases())
            ->reject(fn (DocumentStatus $s): bool => $s === DocumentStatus::OnHold) // paused time isn't a "slow stage"
            ->map(fn (DocumentStatus $s): array => [
                'label' => $s->label(),
                'hours' => isset($count[$s->value]) && $count[$s->value] > 0
                    ? round($sum[$s->value] / $count[$s->value], 1)
                    : 0.0,
                'band' => $s->band(),
            ])
            ->values();
    }

    /** "What volume by type?" — created in window, top 6. */
    public function typeBreakdown(): Collection
    {
        return $this->scoped()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('document_type, COUNT(*) as total')
            ->groupBy('document_type')
            ->orderByDesc('total')
            ->take(6)
            ->get()
            ->map(fn ($r): array => ['label' => $r->document_type, 'value' => (int) $r->total]);
    }

    // ── Region D: supporting data ────────────────────────────────────────────

    /** "Exactly what needs attention." — overdue active docs, worst first. */
    public function atRisk(int $limit = 12): Collection
    {
        return $this->activeDocuments(['assignedTo'])
            ->filter->isOverdue()
            ->map(function (Document $doc): array {
                $stage = $doc->statusEnum();
                $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;
                $elapsed = abs($anchor->getTimestamp() - now()->getTimestamp()) / 3600;

                return [
                    'document' => $doc,
                    'stage' => $stage,
                    'assignee' => $doc->assignedTo?->name,
                    'hours_over' => round($elapsed - ($stage->slaHours() ?? 0), 1),
                    'url' => route('track.show', $doc->tracking_number),
                ];
            })
            ->sortByDesc('hours_over')
            ->take($limit)
            ->values();
    }

    /** Ranked widget: staff with the lowest average processing time (completed in window). */
    public function fastestStaff(int $limit = 5): Collection
    {
        $docs = $this->scoped()
            ->whereNotNull('assigned_to')
            ->whereBetween('completed_at', [$this->from, $this->to])
            ->get(['assigned_to', 'created_at', 'completed_at']);

        if ($docs->isEmpty()) {
            return collect();
        }

        $names = User::whereIn('id', $docs->pluck('assigned_to')->unique())->pluck('name', 'id');

        return $docs->groupBy('assigned_to')
            ->map(fn (Collection $group, $id): array => [
                'name' => $names[$id] ?? 'Unknown',
                'count' => $group->count(),
                'avg_days' => round($group->avg(
                    fn (Document $d): float|int => abs($d->completed_at->getTimestamp() - $d->created_at->getTimestamp()) / 86400
                ), 1),
            ])
            ->sortBy('avg_days')
            ->take($limit)
            ->values();
    }

    /**
     * Org-wide system throughput: documents processed per day over 26 weeks,
     * shaped for the contribution-style heatmap (weeks × days). "Processed" =
     * completed_at, falling back to created_at for never-completed documents.
     */
    public function throughputHeatmap(): array
    {
        $since = now()->startOfWeek(Carbon::SUNDAY)->subWeeks(25);

        $counts = $this->scoped()
            ->where(fn ($q) => $q->where('completed_at', '>=', $since)->orWhere('created_at', '>=', $since))
            ->get(['created_at', 'completed_at'])
            ->groupBy(fn (Document $d) => ($d->completed_at ?? $d->created_at)->toDateString())
            ->map->count();

        return [
            'counts' => $counts->all(),
            'max' => $counts->max() ?? 0,
            'total' => $counts->sum(),
            'since' => $since->toDateString(),
        ];
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** Base query carrying the document-type "slice" filter only. */
    private function scoped(): Builder
    {
        return Document::query()
            ->when($this->documentType, fn ($q) => $q->where('document_type', $this->documentType));
    }

    /** Active (non-completed) documents in the slice, hydrated for PHP-side SLA checks. */
    private function activeDocuments(array $with = []): Collection
    {
        return $this->scoped()
            ->with($with)
            ->whereIn('status', DocumentStatus::activeValues())
            ->get();
    }

    private function countCreated(Carbon $from, Carbon $to): int
    {
        return $this->scoped()->whereBetween('created_at', [$from, $to])->count();
    }

    private function countCompleted(Carbon $from, Carbon $to): int
    {
        return $this->scoped()->whereBetween('completed_at', [$from, $to])->count();
    }

    private function avgProcessingDays(Carbon $from, Carbon $to): ?float
    {
        $docs = $this->scoped()
            ->whereBetween('completed_at', [$from, $to])
            ->whereNotNull('completed_at')
            ->get(['created_at', 'completed_at']);

        if ($docs->isEmpty()) {
            return null;
        }

        return round($docs->avg(
            fn (Document $d): float|int => abs($d->completed_at->getTimestamp() - $d->created_at->getTimestamp()) / 86400
        ), 1);
    }

    private function onTimeRate(Carbon $from, Carbon $to): ?int
    {
        $docs = $this->scoped()
            ->whereBetween('completed_at', [$from, $to])
            ->get(['sla_breached_at']);

        if ($docs->isEmpty()) {
            return null;
        }

        return (int) round($docs->whereNull('sla_breached_at')->count() / $docs->count() * 100);
    }

    /** @return array<string,int> date => count, every day in the window present */
    private function dailyCreated(): array
    {
        return $this->dailySeries('created_at');
    }

    /** @return array<string,int> */
    private function dailyCompleted(): array
    {
        return $this->dailySeries('completed_at');
    }

    /** @return array<string,int> */
    private function dailySeries(string $column): array
    {
        $raw = $this->scoped()
            ->whereBetween($column, [$this->from, $this->to])
            ->selectRaw("DATE($column) as d, COUNT(*) as total")
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        foreach (Date::parse($this->from)->daysUntil($this->to) as $day) {
            $series[$day->toDateString()] = (int) ($raw[$day->toDateString()] ?? 0);
        }

        return $series;
    }

    /**
     * Honest "active as of end of day D" trend: documents created on/before D and
     * not yet completed by D. Derived purely from created_at/completed_at.
     *
     * @return array<int,int>
     */
    private function dailyActive(): array
    {
        $docs = $this->scoped()
            ->where('created_at', '<=', $this->to)
            ->get(['created_at', 'completed_at']);

        $series = [];
        foreach (Date::parse($this->from)->daysUntil($this->to) as $day) {
            $eod = $day->copy()->endOfDay();
            $series[] = $docs->filter(fn (Document $d): bool => $d->created_at <= $eod
                && (! $d->completed_at || $d->completed_at > $eod))->count();
        }

        return $series;
    }

    /** @return array<string,int> week-start date => count */
    private function bucketByWeek(string $column, Carbon $from, Carbon $to): array
    {
        $docs = $this->scoped()
            ->whereBetween($column, [$from, $to])
            ->whereNotNull($column)
            ->pluck($column);

        $buckets = [];
        foreach ($docs as $ts) {
            $key = Date::parse($ts)->startOfWeek(Carbon::SUNDAY)->toDateString();
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
    }

    private function emptyStageSeries(): Collection
    {
        return collect(DocumentStatus::cases())
            ->reject(fn (DocumentStatus $s): bool => $s === DocumentStatus::OnHold)
            ->map(fn (DocumentStatus $s): array => [
                'label' => $s->label(),
                'hours' => 0.0,
                'band' => $s->band(),
            ])
            ->values();
    }

    /**
     * Period-over-period delta. Returns null when there is no meaningful basis.
     *
     * @return array{dir:string,pct:?int,abs:int|float}|null
     */
    private function delta(int|float $now, int|float $prev, bool $lowerIsBetter = false): ?array
    {
        if ($prev == 0) {
            return $now == 0 ? null : ['dir' => 'up', 'good' => ! $lowerIsBetter, 'pct' => null, 'abs' => $now];
        }

        $pct = (int) round(($now - $prev) / $prev * 100);
        $rising = $now >= $prev;

        return [
            // "dir" is the GOOD/BAD direction for colour; "up"/"down" for the arrow.
            'dir' => $rising ? 'up' : 'down',
            'good' => $lowerIsBetter ? ! $rising : $rising,
            'pct' => abs($pct),
            'abs' => round($now - $prev, 1),
        ];
    }
}
