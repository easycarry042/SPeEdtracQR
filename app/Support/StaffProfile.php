<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\StaffHighlight;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Spatie\Activitylog\Models\Activity;

/**
 * Read model for a staff member's profile page (Direction 1b): accountability
 * KPIs, a 12-month completion heatmap, derived presence, and a merged activity
 * feed (manual highlights + auto-logged activity-log events).
 */
class StaffProfile
{
    public function __construct(private readonly User $user) {}

    /** Accountability KPIs shown in the identity rail. */
    public function kpis(): array
    {
        $completedDocs = Document::where('assigned_to', $this->user->id)
            ->where('status', DocumentStatus::Completed->value)
            ->get(['created_at', 'completed_at', 'sla_breached_at']);

        $completed = $completedDocs->count();

        $avgDays = $completedDocs
            ->filter(fn ($d): bool => $d->completed_at && $d->created_at)
            // Raw timestamp delta avoids Carbon version sign ambiguity.
            ->avg(fn ($d): float|int => abs($d->completed_at->getTimestamp() - $d->created_at->getTimestamp()) / 86400);

        $onTime = $completed > 0
            ? round($completedDocs->whereNull('sla_breached_at')->count() / $completed * 100)
            : null;

        return [
            'completed' => $completed,
            'assigned' => $this->assignedCount(),
            'avg_processing_days' => $avgDays !== null ? round($avgDays, 1) : null,
            'sla_rate' => $onTime, // percent, or null when no completions yet
        ];
    }

    public function assignedCount(): int
    {
        return Document::where('assigned_to', $this->user->id)
            ->whereIn('status', DocumentStatus::activeValues())
            ->count();
    }

    /**
     * When this person was last here.
     *
     * users.last_active_at is stamped by TrackLastActive on every page the
     * person opens. The activity log is only a fallback, for an account that
     * has not been seen since that column existed — on its own it answers
     * "when did they last change something", which is why a staff member
     * reading the site all morning used to show as an hour idle.
     */
    public function lastActiveAt(): ?Carbon
    {
        if ($this->user->last_active_at) {
            return $this->user->last_active_at;
        }

        $ts = Activity::where('causer_type', $this->user->getMorphClass())
            ->where('causer_id', $this->user->id)
            ->max('created_at');

        return $ts ? Date::parse($ts) : null;
    }

    /**
     * Completions per day over the last 12 months, plus the figures that read
     * underneath the grid.
     *
     * The grid alone only says "busy sometimes". The strip under it answers
     * the questions people actually bring to a profile: what does this person
     * mostly handle, are they working now, and what does a good run look like.
     *
     * @return array{counts: array<string, int>, max: int, total: int, since: string,
     *               last30: int, busiest_type: ?string, busiest_type_count: int,
     *               peak_day: ?string, peak_day_count: int,
     *               peak_month: ?string, peak_month_count: int,
     *               current_streak: int, longest_streak: int}
     */
    public function heatmap(): array
    {
        $since = now()->subYear()->startOfDay();

        $completed = Document::where('assigned_to', $this->user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->get(['completed_at', 'document_type']);

        $counts = $completed
            ->groupBy(fn ($d) => $d->completed_at->toDateString())
            ->map->count();

        $byMonth = $completed
            ->groupBy(fn ($d) => $d->completed_at->format('Y-m'))
            ->map->count();

        $byType = $completed
            ->groupBy('document_type')
            ->map->count()
            ->sortDesc();

        $peakDay = $counts->sortDesc()->keys()->first();
        $peakMonth = $byMonth->sortDesc()->keys()->first();

        [$current, $longest] = $this->streaks($counts->keys()->all());

        return [
            'counts' => $counts->all(),       // [ 'Y-m-d' => n ]
            'max' => $counts->max() ?? 0,
            'total' => $counts->sum(),
            'since' => $since->toDateString(),

            'last30' => $completed->filter(
                fn ($d): bool => $d->completed_at->gte(now()->subDays(30)->startOfDay())
            )->count(),

            'busiest_type' => $byType->keys()->first(),
            'busiest_type_count' => (int) ($byType->first() ?? 0),

            'peak_day' => $peakDay,
            'peak_day_count' => (int) ($peakDay ? $counts[$peakDay] : 0),

            'peak_month' => $peakMonth,
            'peak_month_count' => (int) ($peakMonth ? $byMonth[$peakMonth] : 0),

            'current_streak' => $current,
            'longest_streak' => $longest,
        ];
    }

    /**
     * Consecutive days finishing at least one request.
     *
     * The current streak is allowed to end yesterday: at 9am on a working day
     * nobody has completed anything yet, and zeroing a fortnight's run because
     * of that would be wrong.
     *
     * @param  list<string>  $days  'Y-m-d', any order
     * @return array{0: int, 1: int} [current, longest]
     */
    private function streaks(array $days): array
    {
        if ($days === []) {
            return [0, 0];
        }

        sort($days);

        $longest = 1;
        $run = 1;
        $previous = null;

        foreach ($days as $day) {
            if ($previous !== null) {
                $run = Date::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
                $longest = max($longest, $run);
            }

            $previous = $day;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $current = in_array($previous, [$today, $yesterday], true) ? $run : 0;

        return [$current, $longest];
    }

    /** Documents currently assigned to this user (registry list + at-risk flag). */
    public function assigned(): Collection
    {
        return Document::where('assigned_to', $this->user->id)
            ->whereIn('status', DocumentStatus::activeValues())
            ->with('creator:id,name')
            ->latest('status_changed_at')
            ->get()
            ->each(fn ($d) => $d->setAttribute('overdue', $d->isOverdue()));
    }

    /** Recently completed documents. */
    public function completions(int $limit = 20): Collection
    {
        return Document::where('assigned_to', $this->user->id)
            ->where('status', DocumentStatus::Completed->value)
            ->with('creator:id,name')
            ->latest('completed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * De-noised activity feed. Staff posts and completions are prominent,
     * ungrouped cards. All other system/activity-log events are collapsed into
     * ONE chunk per document (a "N updates" card that expands). This is purely a
     * display grouping — the raw activity log is untouched.
     *
     * @return Collection<int, array>
     */
    public function feed(int $limit = 40): Collection
    {
        // 1) Manual staff posts — prominent.
        $manual = $this->user->highlights()
            ->with(['document:id,tracking_number,document_type,status,source,created_by,created_at', 'document.creator:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn (StaffHighlight $h): array => [
                'kind' => 'manual',
                'type' => $h->highlight_type,
                'body' => $h->body,
                'author' => $this->user->name,
                'document' => $this->documentChip($h->document),
                'at' => $h->created_at,
            ]);

        // 2) Completions — prominent.
        $completions = $this->completions($limit)->map(fn (Document $d): array => [
            'kind' => 'completion',
            'author' => $this->user->name,
            'document' => $this->documentChip($d),
            'at' => $d->completed_at,
        ]);

        // 3) System/activity-log events, collapsed into one chunk per document
        //    (the "→ Completed" transition is excluded — it's shown as a completion card).
        $groups = Activity::where('causer_type', $this->user->getMorphClass())
            ->where('causer_id', $this->user->id)
            ->where('subject_type', (new Document)->getMorphClass())
            ->latest()
            ->limit(200)
            ->get(['subject_id', 'description', 'properties', 'created_at'])
            ->reject(fn (Activity $a): bool => data_get($a->properties, 'to') === DocumentStatus::Completed->value
                || data_get($a->properties, 'attributes.status') === DocumentStatus::Completed->value)
            ->groupBy('subject_id')
            ->map(function (Collection $items, $docId): ?array {
                $doc = Document::find($docId); // subject may be soft-deleted
                if (! $doc) {
                    return null;
                }
                $ordered = $items->sortByDesc('created_at')->values();

                return [
                    'kind' => 'system_group',
                    'author' => 'System',
                    'document' => $this->documentChip($doc),
                    'count' => $ordered->count(),
                    'latest_status' => $doc->status,
                    'at' => $ordered->first()?->created_at,
                    'items' => $ordered->map(fn (Activity $a): array => [
                        'body' => $a->description,
                        'at' => $a->created_at,
                    ])->all(),
                ];
            })
            ->filter()
            ->values();

        return $manual->concat($completions)->concat($groups)
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }

    private function documentChip(?Document $doc): ?array
    {
        if (! $doc instanceof Document) {
            return null;
        }

        return [
            'tracking_number' => $doc->tracking_number,
            'document_type' => $doc->document_type,
            'status' => $doc->status,
            'source' => $doc->source,
            'created_by_name' => $doc->creator?->name,
            'created_at' => $doc->created_at,
            'url' => route('track.show', $doc->tracking_number),
        ];
    }
}
