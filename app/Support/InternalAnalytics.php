<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model for the INTERNAL side of the super-admin command center: requests
 * one office files with another, travelling a chain of endorsement hops.
 *
 * They are measured by their own lifecycle rather than the citizen stage
 * clocks: a request is overdue when the office now holding it has had it
 * longer than that office's turnaround (Department::internalStepHours()), and
 * a request returned for revision is waiting on the office that filed it.
 *
 * Tiles read one cohort — internal requests filed in the selected window, by
 * where each is now — so they add back to the total, as on the citizen side.
 */
class InternalAnalytics
{
    private readonly Carbon $prevFrom;

    private readonly Carbon $prevTo;

    /** @var Collection<int, Document>|null open requests, hydrated once */
    private ?Collection $open = null;

    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        // One office's internal requests — filed by it or routed through it
        // (Document::involvingOffice) — for a supervisor's Reports; null = all.
        private readonly ?int $departmentId = null,
    ) {
        $seconds = $this->from->diffInSeconds($this->to);
        $this->prevTo = $this->from->copy()->subSecond();
        $this->prevFrom = $this->prevTo->copy()->subSeconds($seconds);
    }

    /**
     * @return array{filed: int, filed_prev: int, moving: int, returned: int, completed: int, denied: int, avg_turnaround: ?float, avg_sample: int, overdue: int}
     */
    public function kpis(): array
    {
        $cohort = $this->cohortCounts($this->from, $this->to);
        $completedInWindow = $this->scoped()
            ->whereBetween('completed_at', [$this->from, $this->to])
            ->get(['created_at', 'completed_at']);

        return [
            'filed' => $cohort['total'],
            'filed_prev' => $this->cohortCounts($this->prevFrom, $this->prevTo)['total'],
            'moving' => $cohort['moving'],
            'returned' => $cohort['returned'],
            'completed' => $cohort['completed'],
            'denied' => $cohort['denied'],
            // Filing to "done", in days, for requests finished in the window.
            'avg_turnaround' => $completedInWindow->isEmpty() ? null : round($completedInWindow->avg(
                fn (Document $d): float => abs($d->completed_at->getTimestamp() - $d->created_at->getTimestamp()) / 86400
            ), 3),
            'avg_sample' => $completedInWindow->count(),
            // Open now, whenever filed.
            'overdue' => $this->waiting()->where('overdue', true)->count(),
        ];
    }

    /**
     * Every open request and where it is waiting, longest wait first. A request
     * at an office is on that office's clock; one returned for revision is
     * waiting on the office that filed it, with no deadline.
     *
     * @return Collection<int, array{document: Document, at: string, at_code: ?string, returned: bool, waited_hours: float, waited: string, limit_hours: ?int, overdue: bool, url: string}>
     */
    public function waiting(): Collection
    {
        return $this->openDocuments()
            ->map(function (Document $doc): array {
                $returned = $doc->statusEnum() === DocumentStatus::Returned;
                $step = $returned
                    ? $doc->requestSteps->where('status', RequestStep::STATUS_RETURNED)->sortByDesc('acted_at')->first()
                    : $doc->requestSteps->firstWhere('status', RequestStep::STATUS_CURRENT);

                $since = $returned ? ($step?->acted_at ?? $doc->status_changed_at) : ($step?->started_at ?? $doc->created_at);
                $hours = $since ? abs(now()->getTimestamp() - $since->getTimestamp()) / 3600 : 0.0;
                $office = $returned ? $doc->requestingDepartment : $step?->department;
                $limit = $returned ? null : $office?->internalStepHours();

                return [
                    'document' => $doc,
                    'at' => $office?->name ?? 'No office',
                    'at_code' => $office?->code,
                    'returned' => $returned,
                    'waited_hours' => round($hours, 1),
                    'waited' => Duration::hours($hours),
                    'limit_hours' => $limit,
                    'overdue' => $limit !== null && $hours > $limit,
                    'url' => route('requests.index', ['open' => $doc->id]),
                ];
            })
            ->sortByDesc('waited_hours')
            ->values();
    }

    /**
     * "Where they're waiting" — open requests per office holding them, with the
     * longest wait and how many are past that office's turnaround. The
     * bottleneck view: one row per office, busiest first.
     *
     * @return Collection<int, array{label: string, code: ?string, count: int, overdue: int, longest: string, limit: ?string, returned: bool}>
     */
    public function waitingByOffice(): Collection
    {
        return $this->waiting()
            ->groupBy(fn (array $row): string => ($row['returned'] ? 'returned:' : 'at:').$row['at'])
            ->map(fn (Collection $rows): array => [
                'label' => $rows->first()['returned'] ? $rows->first()['at'].' (returned to them)' : $rows->first()['at'],
                'code' => $rows->first()['at_code'],
                'count' => $rows->count(),
                'overdue' => $rows->where('overdue', true)->count(),
                'longest' => Duration::hours($rows->max('waited_hours')),
                'limit' => $rows->first()['limit_hours'] ? Duration::hours($rows->first()['limit_hours']) : null,
                'returned' => $rows->first()['returned'],
            ])
            ->sortBy([
                fn (array $a, array $b): int => $b['overdue'] <=> $a['overdue'],
                fn (array $a, array $b): int => $b['count'] <=> $a['count'],
            ])
            ->values();
    }

    /**
     * "How long each office takes" — the average time an office held a request
     * before acting on it, over hops closed in the window, against its own
     * turnaround.
     *
     * @return Collection<int, array{label: string, code: ?string, hops: int, avg: string, avg_hours: float, limit: string, over: bool}>
     */
    public function officeTurnaround(): Collection
    {
        $steps = RequestStep::query()
            ->with('department')
            ->whereNotNull('started_at')
            ->whereNotNull('acted_at')
            ->whereBetween('acted_at', [$this->from, $this->to])
            ->whereHas('document', fn (Builder $q) => $this->departmentId
                ? $q->involvingOffice($this->departmentId)
                : $q->internal())
            ->get(['id', 'department_id', 'started_at', 'acted_at']);

        return $steps
            ->groupBy('department_id')
            ->map(function (Collection $hops): array {
                $department = $hops->first()->department;
                $avg = $hops->avg(fn (RequestStep $s): float => abs($s->acted_at->getTimestamp() - $s->started_at->getTimestamp()) / 3600);
                $limit = $department?->internalStepHours() ?? Department::DEFAULT_INTERNAL_STEP_HOURS;

                return [
                    'label' => $department?->name ?? 'Unknown office',
                    'code' => $department?->code,
                    'hops' => $hops->count(),
                    'avg' => Duration::hours($avg),
                    'avg_hours' => round($avg, 1),
                    'limit' => Duration::hours($limit),
                    'over' => $avg > $limit,
                ];
            })
            ->sortByDesc('avg_hours')
            ->values();
    }

    /**
     * "Filed by office" — who files internal requests, in the window.
     *
     * @return Collection<int, array{label: string, code: ?string, value: int, pct: int}>
     */
    public function byRequestingOffice(): Collection
    {
        $counts = $this->scoped()
            ->whereNotNull('requesting_department_id')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('requesting_department_id, COUNT(*) as total')
            ->groupBy('requesting_department_id')
            ->pluck('total', 'requesting_department_id')
            ->map(fn ($n): int => (int) $n);

        if ($counts->isEmpty()) {
            return collect();
        }

        $max = max(1, (int) $counts->max());

        return Department::whereIn('id', $counts->keys())->get(['id', 'name', 'code'])
            ->map(fn (Department $d): array => [
                'label' => $d->name,
                'code' => $d->code,
                'value' => $counts[$d->id],
                'pct' => (int) round($counts[$d->id] / $max * 100),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /**
     * Filed vs completed per week.
     *
     * @return list<array{label: string, created: int, completed: int}>
     */
    public function throughput(): array
    {
        $bucket = function (string $column): array {
            $buckets = [];
            foreach ($this->scoped()->whereBetween($column, [$this->from, $this->to])->pluck($column) as $ts) {
                $key = Carbon::parse($ts)->startOfWeek(Carbon::SUNDAY)->toDateString();
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }

            return $buckets;
        };

        $created = $bucket('created_at');
        $completed = $bucket('completed_at');

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

    /** @return Collection<int, array<string, mixed>> */
    public function recentActivity(int $limit = 25): Collection
    {
        return ActivityLine::recentOnRequests(internal: true, limit: $limit, departmentId: $this->departmentId);
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function scoped(): Builder
    {
        return $this->departmentId
            ? Document::query()->involvingOffice($this->departmentId)
            : Document::query()->internal();
    }

    /** @return Collection<int, Document> */
    private function openDocuments(): Collection
    {
        return $this->open ??= $this->scoped()
            ->whereIn('status', DocumentStatus::activeValues())
            ->with(['requestSteps.department', 'requestingDepartment'])
            ->get();
    }

    /**
     * Internal requests filed in [$from, $to], by where each one is now.
     *
     * @return array{total: int, moving: int, returned: int, completed: int, denied: int}
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
        $returned = $counts[DocumentStatus::Returned->value] ?? 0;
        $completed = $counts[DocumentStatus::Completed->value] ?? 0;
        $denied = $counts[DocumentStatus::Denied->value] ?? 0;
        // Closed by the office that filed it: no longer moving, and not a denial.
        $withdrawn = $counts[DocumentStatus::Withdrawn->value] ?? 0;

        return [
            'total' => $total,
            'moving' => max(0, $total - $returned - $completed - $denied - $withdrawn),
            'returned' => $returned,
            'completed' => $completed,
            'denied' => $denied,
        ];
    }
}
