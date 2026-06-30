<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\StaffHighlight;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Read model for a staff member's profile page (Direction 1b): accountability
 * KPIs, a 12-month completion heatmap, derived presence, and a merged activity
 * feed (manual highlights + auto-logged activity-log events).
 */
class StaffProfile
{
    public function __construct(private User $user) {}

    /** Accountability KPIs shown in the identity rail. */
    public function kpis(): array
    {
        $completedDocs = Document::where('assigned_to', $this->user->id)
            ->where('status', DocumentStatus::Completed->value)
            ->get(['created_at', 'completed_at', 'sla_breached_at']);

        $completed = $completedDocs->count();

        $avgDays = $completedDocs
            ->filter(fn ($d) => $d->completed_at && $d->created_at)
            // Raw timestamp delta avoids Carbon version sign ambiguity.
            ->avg(fn ($d) => abs($d->completed_at->getTimestamp() - $d->created_at->getTimestamp()) / 86400);

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

    /** "Active now" / "last active" derived from the latest activity-log entry. */
    public function lastActiveAt(): ?Carbon
    {
        $ts = Activity::where('causer_type', $this->user->getMorphClass())
            ->where('causer_id', $this->user->id)
            ->max('created_at');

        return $ts ? Carbon::parse($ts) : null;
    }

    /** Completions per day over the last 12 months for the contribution heatmap. */
    public function heatmap(): array
    {
        $since = now()->subYear()->startOfDay();

        $counts = Document::where('assigned_to', $this->user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->get(['completed_at'])
            ->groupBy(fn ($d) => $d->completed_at->toDateString())
            ->map->count();

        return [
            'counts' => $counts->all(),       // [ 'Y-m-d' => n ]
            'max' => $counts->max() ?? 0,
            'total' => $counts->sum(),
            'since' => $since->toDateString(),
        ];
    }

    /** Documents currently assigned to this user (registry list + at-risk flag). */
    public function assigned(): Collection
    {
        return Document::where('assigned_to', $this->user->id)
            ->whereIn('status', DocumentStatus::activeValues())
            ->latest('status_changed_at')
            ->get()
            ->each(fn ($d) => $d->setAttribute('overdue', $d->isOverdue()));
    }

    /** Recently completed documents. */
    public function completions(int $limit = 20): Collection
    {
        return Document::where('assigned_to', $this->user->id)
            ->where('status', DocumentStatus::Completed->value)
            ->latest('completed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Merged activity feed: manual highlights + auto-logged activity-log events
     * caused by this user, normalised and sorted newest-first.
     *
     * @return Collection<int, array>
     */
    public function feed(int $limit = 30): Collection
    {
        $manual = $this->user->highlights()
            ->with('document:id,tracking_number,document_type,status')
            ->limit($limit)
            ->get()
            ->map(fn (StaffHighlight $h) => [
                'kind' => 'manual',
                'type' => $h->highlight_type,
                'body' => $h->body,
                'author' => $this->user->name,
                'document' => $this->documentChip($h->document),
                'at' => $h->created_at,
            ]);

        $auto = Activity::where('causer_type', $this->user->getMorphClass())
            ->where('causer_id', $this->user->id)
            ->where('subject_type', (new Document)->getMorphClass())
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Activity $a) {
                $doc = $a->subject; // may be null if soft-deleted
                $props = $a->properties ?? collect();

                return [
                    'kind' => 'auto',
                    'type' => 'system',
                    'body' => $a->description,
                    'author' => 'System',
                    'document' => $doc ? $this->documentChip($doc) : null,
                    'status' => $props['to'] ?? ($doc?->status),
                    'at' => $a->created_at,
                ];
            });

        return $manual->concat($auto)
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }

    private function documentChip(?Document $doc): ?array
    {
        if (! $doc) {
            return null;
        }

        return [
            'tracking_number' => $doc->tracking_number,
            'document_type' => $doc->document_type,
            'status' => $doc->status,
            'url' => route('track.show', $doc->tracking_number),
        ];
    }
}
