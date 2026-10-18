<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Department;
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
 * snapshot metrics (status distribution, active load, overdue) describe "now".
 *
 * The tile row and the status donut read one COHORT: the requests filed inside
 * the window, split by where each one is now. They used to mix the two kinds —
 * Total and Completed from the window, Pending and In Progress from all time —
 * so the four tiles looked like they added up and only did by coincidence.
 *
 * Whether an open request is on time, at risk or overdue is decided in one
 * place, slaState(). The tile, the breakdown panel and the attention list each
 * used to work it out themselves, and the page showed 15, 0 and 12 for the
 * same thing.
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
        // The tile row: requests filed in the window, by where each is now, so
        // Pending + In Progress + Completed (+ Denied) add back to Total.
        $cohort = $this->cohortCounts($this->from, $this->to);
        $cohortPrev = $this->cohortCounts($this->prevFrom, $this->prevTo);

        // "All open right now", whenever filed — the charts and the CSV read it.
        $open = $this->activeDocuments();
        $states = $open->map(fn (Document $doc): ?string => $this->slaState($doc));
        $overdue = $states->filter(fn (?string $state): bool => $state === 'overdue')->count();
        $atRisk = $states->filter(fn (?string $state): bool => $state === 'at_risk')->count();

        $avgNow = $this->avgProcessingDays($this->from, $this->to);
        $avgPrev = $this->avgProcessingDays($this->prevFrom, $this->prevTo);

        return [
            'total' => [
                'value' => $cohort['total'],
                'delta' => $this->delta($cohort['total'], $cohortPrev['total']),
                'spark' => array_values($this->dailyCreated()),
            ],
            'completed' => [
                'value' => $cohort['completed'],
                'delta' => $this->delta($cohort['completed'], $cohortPrev['completed']),
                'spark' => array_values($this->dailyCompleted()),
            ],
            'active' => [
                'value' => $open->count(),
                'spark' => $this->dailyActive(),
            ],
            'pending' => [
                'value' => $cohort['pending'],
            ],
            'in_progress' => [
                'value' => $cohort['in_progress'],
            ],
            'denied' => [
                'value' => $cohort['denied'],
            ],
            // Past their stage's processing time, open now.
            'overdue' => [
                'value' => $overdue,
                'pill' => $overdue > 0 ? 'breach' : 'ok',
            ],
            // Not late yet, but three quarters of the way there.
            'at_risk' => [
                'value' => $atRisk,
                'pill' => $atRisk > 0 ? 'watch' : 'ok',
            ],
            'avg_processing' => [
                'value' => $avgNow,
                // Lower is better, so invert the delta direction for display.
                'delta' => $this->comparableDelta($avgNow, $avgPrev, lowerIsBetter: true),
            ],
        ];
    }

    // ── Region C: analytics grid ─────────────────────────────────────────────

    /** "Requests Filed vs. Completed" — created vs completed per week. */
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
        // The tiles' cohort — requests filed in the window — so this panel is
        // the tiles broken down by stage and its rows add back to Total.
        $counts = $this->scoped()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(DocumentStatus::cases())->map(fn (DocumentStatus $s): array => [
            'label' => $s->label(),
            'value' => (int) ($counts[$s->value] ?? 0),
            'band' => $s->band(),
        ]);
    }

    /** "Staff Workload" — completed (in window) + current active load per assignee. */
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

    /** "Open Requests vs. Deadline" — split open docs + the stage with the most overdue. */
    public function bottlenecks(): array
    {
        $active = $this->activeDocuments();

        $onTime = 0;
        $atRisk = 0;
        $breached = 0;
        $breachedByStage = [];

        foreach ($active as $doc) {
            $state = $this->slaState($doc);

            if ($state === 'overdue') {
                $breached++;
                $label = $doc->statusEnum()->label();
                $breachedByStage[$label] = ($breachedByStage[$label] ?? 0) + 1;
            } elseif ($state === 'at_risk') {
                $atRisk++;
            } else {
                // On time, or no clock running (held, returned).
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
     * "Average Time per Stage" — hours spent in each stage on average, reconstructed
     * from the activity log by diffing consecutive status-change timestamps. Only
     * CLOSED intervals (a stage the document has already left) are counted.
     */
    public function timeByStage(): Collection
    {
        $docs = $this->scoped()->get(['id', 'created_at', 'status']);
        if ($docs->isEmpty()) {
            return $this->emptyStageSeries();
        }

        $sum = [];   // stage value => total hours
        $count = []; // stage value => closed intervals

        foreach ($this->stageIntervals($docs) as $intervals) {
            foreach ($intervals as [$status, $hours]) {
                $sum[$status] = ($sum[$status] ?? 0) + $hours;
                $count[$status] = ($count[$status] ?? 0) + 1;
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

    /** "Volume by Request Type" — created in window, top 6. */
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

    /**
     * "Document Volume by Department" — how much work each office is carrying.
     *
     * The bar is read against the busiest office, not against the total: the
     * frame draws one row at 100% and the rest below it, which is a comparison
     * between offices rather than a share of a whole, so the panel prints each
     * office's COUNT beside its bar (it used to print the bar's percentage,
     * which read as a share: 100% / 10% / 10%). Requests filed in the window,
     * like the tiles above it. Offices with none are left out.
     *
     * @return Collection<int, array{label: string, code: ?string, value: int, pct: int, busiest: bool}>
     */
    public function departmentVolume(int $limit = 6): Collection
    {
        $counts = $this->scoped()
            ->whereNotNull('department_id')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')
            ->orderByDesc('total')
            ->take($limit)
            ->pluck('total', 'department_id');

        if ($counts->isEmpty()) {
            return collect();
        }

        $departments = Department::whereIn('id', $counts->keys())->get(['id', 'name', 'code'])->keyBy('id');
        $max = max(1, (int) $counts->max());

        return $counts->map(fn ($total, $id): array => [
            'label' => $departments[$id]->name ?? 'Unassigned office',
            'code' => $departments[$id]->code ?? null,
            'value' => (int) $total,
            'pct' => (int) round($total / $max * 100),
            'busiest' => (int) $total === $max,
        ])->values();
    }

    /**
     * The audit strip at the foot of the command center: who did what, latest
     * first. Read straight off the activity log rather than a second table, so
     * it can never drift from the full Audit Log page.
     *
     * @return Collection<int, array{at: Carbon, user: string, role: string, action: string, subject: ?string, detail: ?string}>
     */
    public function recentActivity(int $limit = 25): Collection
    {
        return Activity::with(['causer.roles', 'subject'])
            // Work on requests, not sign-ins: the admin's own logins filled the
            // strip ("Super Admin · Signed In · 127.0.0.1" five times over).
            // They stay on the full Audit Log page.
            ->where(fn ($query) => $query->whereNull('log_name')->orWhere('log_name', '!=', 'auth'))
            // Several entries can share a second (a create and the first status
            // change, say), so the id breaks the tie — otherwise "newest first"
            // is whatever order the driver happens to return.
            ->latest()
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(function (Activity $entry): array {
                $properties = $entry->properties ?? collect();
                $changed = collect($properties->get('attributes') ?? []);
                $subject = $entry->subject;
                $tracking = $subject instanceof Document ? $subject->tracking_number : null;

                [$action, $detail] = match (true) {
                    $entry->event === 'created' => ['Request Created', $changed->get('document_type')],
                    $changed->has('status') => [
                        'Status Updated',
                        '→ '.DocumentStatus::fromLoose($changed->get('status'))->label(),
                    ],
                    $changed->has('assigned_to') => [
                        'Staff Assigned',
                        $changed->get('assigned_to')
                            ? '→ '.(User::find($changed->get('assigned_to'))?->name ?? 'Unknown')
                            : '→ Unassigned',
                    ],
                    default => [ucfirst((string) ($entry->description ?: $entry->event ?: 'Change')), null],
                };

                $role = $entry->causer?->roles->first()?->name;

                return [
                    'at' => $entry->created_at,
                    'user' => $entry->causer?->name ?? 'System',
                    'role' => $role ? str_replace('_', ' ', ucwords($role, '_')) : 'System',
                    'action' => $action,
                    'subject' => $tracking,
                    'detail' => $detail,
                ];
            });
    }

    // ── Region D: supporting data ────────────────────────────────────────────

    /**
     * "Needs Attention" — every overdue open document, worst
     * first. Uncapped: the panel shows the first rows and states the true
     * total. It used to stop at 12 and then report "12 overdue" while the tile
     * above said 15.
     */
    public function atRisk(): Collection
    {
        return $this->activeDocuments(['assignedTo'])
            ->filter(fn (Document $doc): bool => $this->slaState($doc) === 'overdue')
            ->map(function (Document $doc): array {
                $stage = $doc->statusEnum();
                $hoursOver = $this->hoursSince($this->clockStart($doc)) - ($stage->slaHours() ?? 0);

                return [
                    'document' => $doc,
                    'stage' => $stage,
                    'assignee' => $doc->assignedTo?->name,
                    'hours_over' => round($hoursOver, 1),
                    'over' => Duration::hours($hoursOver),
                    'url' => route('track.show', $doc->tracking_number),
                ];
            })
            ->sortByDesc('hours_over')
            ->values();
    }

    /**
     * Documents parked On Hold past their own hold deadline.
     *
     * These never reach atRisk(): a hold sets the stage SLA to null, so
     * isOverdue() is false by design. Without this list a stalled hold would be
     * invisible on the command center — the exact blind spot documents:check-holds
     * emails about.
     */
    public function stalledHolds(int $limit = 12): Collection
    {
        return $this->scoped()
            ->with('assignedTo')
            ->where('status', DocumentStatus::OnHold->value)
            ->get()
            ->filter->isHoldOverdue()
            ->map(fn (Document $doc): array => [
                'document' => $doc,
                'assignee' => $doc->assignedTo?->name,
                'blocked_by' => $doc->blocked_by,
                'days_over' => $doc->holdOverdueDays(),
                'held_since' => $doc->held_at,
                'url' => route('track.show', $doc->tracking_number),
            ])
            ->sortByDesc('days_over')
            ->take($limit)
            ->values();
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

    private function avgProcessingDays(Carbon $from, Carbon $to): ?float
    {
        $docs = $this->scoped()
            ->whereBetween('completed_at', [$from, $to])
            ->whereNotNull('completed_at')
            ->get(['created_at', 'completed_at']);

        if ($docs->isEmpty()) {
            return null;
        }

        // Three decimals, not one: a day is 1440 minutes, so rounding to a tenth
        // of a day printed a 53-minute average as "0".
        return round($docs->avg(
            fn (Document $d): float|int => abs($d->completed_at->getTimestamp() - $d->created_at->getTimestamp()) / 86400
        ), 3);
    }

    /**
     * Each document's closed stage intervals, rebuilt from the activity log by
     * diffing consecutive status changes: [doc id => [[status, hours], …]].
     * The first interval starts when the document was filed.
     *
     * @param  Collection<int, Document>  $docs  each with id and created_at
     * @return array<int, list<array{0: string, 1: float}>>
     */
    private function stageIntervals(Collection $docs): array
    {
        $activities = Activity::where('subject_type', (new Document)->getMorphClass())
            ->whereIn('subject_id', $docs->pluck('id'))
            ->orderBy('created_at')
            ->get(['subject_id', 'properties', 'created_at'])
            ->groupBy('subject_id');

        $intervals = [];

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
                    $intervals[$doc->id][] = [
                        $prevStatus,
                        abs($act->created_at->getTimestamp() - $prevTime->getTimestamp()) / 3600,
                    ];
                }

                $prevTime = $act->created_at;
                $prevStatus = $attrs['status'];
            }
        }

        return $intervals;
    }

    /**
     * Where an open document stands against its current stage's clock — the one
     * definition every overdue figure on the page reads.
     *
     * @return 'on_time'|'at_risk'|'overdue'|null null when no clock is running
     *                                            (held, returned, closed)
     */
    private function slaState(Document $doc): ?string
    {
        $stage = $doc->statusEnum();
        $sla = $stage->slaHours();
        $start = $this->clockStart($doc);

        if ($sla === null || $stage->isTerminal() || ! $start) {
            return null;
        }

        $elapsed = $this->hoursSince($start);

        return match (true) {
            $elapsed > $sla => 'overdue',
            $elapsed >= $sla * self::AT_RISK_FRACTION => 'at_risk',
            default => 'on_time',
        };
    }

    /** When the current stage's clock started — the same anchor isOverdue() uses. */
    private function clockStart(Document $doc): ?\DateTimeInterface
    {
        return $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;
    }

    private function hoursSince(\DateTimeInterface $moment): float
    {
        return abs(now()->getTimestamp() - $moment->getTimestamp()) / 3600;
    }

    /**
     * Requests filed in [$from, $to], by where each one is now. Pending,
     * in progress (every open stage after Pending, held and returned
     * included), completed and denied add back to the total.
     *
     * @return array{total: int, pending: int, in_progress: int, completed: int, denied: int}
     */
    private function cohortCounts(Carbon $from, Carbon $to): array
    {
        $counts = $this->scoped()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n): int => (int) $n);

        $total = (int) $counts->sum();
        $pending = $counts[DocumentStatus::Pending->value] ?? 0;
        $completed = $counts[DocumentStatus::Completed->value] ?? 0;
        $denied = $counts[DocumentStatus::Denied->value] ?? 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'in_progress' => max(0, $total - $pending - $completed - $denied),
            'completed' => $completed,
            'denied' => $denied,
        ];
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
    /**
     * A delta for a rate or an average, which can be undefined (no completions
     * in a window). Undefined on either side means no comparison: treating it
     * as 0 printed "▲ +100" for an on-time rate that had nothing to compare to.
     */
    private function comparableDelta(int|float|null $now, int|float|null $prev, bool $lowerIsBetter = false): ?array
    {
        if ($now === null || $prev === null) {
            return null;
        }

        return $this->delta($now, $prev, $lowerIsBetter);
    }

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
