<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Per-person workload figures — open, completed, overdue, last active — read
 * by the Users page and the staff directory. One grouped query per figure, so
 * a page of people costs four queries rather than four per person.
 */
final class StaffLoad
{
    /**
     * @param  Collection<int, int>|array<int, int>  $userIds
     * @return Collection<int, array{assigned: int, completed: int, month: int, overdue: int, last_active: ?string, active_now: bool}>
     */
    public static function for(Collection|array $userIds): Collection
    {
        $ids = collect($userIds)->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $assignedTo = fn () => Document::whereIn('assigned_to', $ids);

        $assigned = $assignedTo()
            ->whereIn('status', DocumentStatus::activeValues())
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $completed = $assignedTo()
            ->where('status', DocumentStatus::Completed->value)
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $completedThisMonth = $assignedTo()
            ->where('status', DocumentStatus::Completed->value)
            ->where('status_changed_at', '>=', now()->startOfMonth())
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        // Overdue = active docs past their per-stage SLA budget. The budget is
        // per-stage PHP logic (DocumentStatus::slaHours), so evaluate the small
        // active set in memory rather than duplicating it in SQL.
        $overdue = $assignedTo()
            ->whereIn('status', DocumentStatus::activeValues())
            ->get(['id', 'assigned_to', 'status', 'status_changed_at', 'updated_at', 'created_at'])
            ->filter(fn (Document $d): bool => $d->isOverdue())
            ->countBy('assigned_to');

        // Presence: the stamp TrackLastActive writes on every page a person
        // opens. The activity log is only a fallback, for accounts not seen
        // since that column existed — on its own it reports the last thing
        // they CHANGED, so anyone merely reading the site read as idle.
        $lastActive = DB::table('users')
            ->whereIn('id', $ids)
            ->whereNotNull('last_active_at')
            ->pluck('last_active_at', 'id');

        $unseen = $ids->reject(fn (int $id): bool => isset($lastActive[$id]))->values();

        if ($unseen->isNotEmpty()) {
            $lastActive = $lastActive->union(
                DB::table('activity_log')
                    ->where('causer_type', User::class)
                    ->whereIn('causer_id', $unseen)
                    ->select('causer_id', DB::raw('MAX(created_at) as last'))
                    ->groupBy('causer_id')
                    ->pluck('last', 'causer_id')
            );
        }

        return $ids->mapWithKeys(fn (int $id): array => [$id => [
            'assigned' => (int) ($assigned[$id] ?? 0),
            'completed' => (int) ($completed[$id] ?? 0),
            'month' => (int) ($completedThisMonth[$id] ?? 0),
            'overdue' => (int) ($overdue[$id] ?? 0),
            // Same five-minute window the profile header uses, so one person
            // cannot read "Active now" on their profile and "Active 1h ago"
            // in the directory at the same moment.
            'last_active' => isset($lastActive[$id])
                ? Date::parse($lastActive[$id])->diffForHumans(short: true)
                : null,
            'active_now' => isset($lastActive[$id])
                && Date::parse($lastActive[$id])->gt(now()->subMinutes(5)),
        ]]);
    }
}
