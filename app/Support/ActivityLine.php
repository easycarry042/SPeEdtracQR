<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * One activity-log row in words, shared by the command center's Recent
 * Activity strip and the Audit Log page so the two never describe the same
 * change differently.
 *
 * The audit log used to print a model change as "updated (Document #19)": the
 * database id rather than the tracking number anyone would search for, and no
 * word on what changed.
 */
final class ActivityLine
{
    /**
     * @return array{action: string, detail: ?string, subject: ?string}
     */
    public static function describe(Activity $entry): array
    {
        $changed = collect(($entry->properties ?? collect())->get('attributes') ?? []);

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
            $entry->event === 'updated' && $changed->isNotEmpty() => [
                'Details Edited',
                $changed->keys()->map(fn (string $field): string => str_replace('_', ' ', $field))->implode(', '),
            ],
            default => [ucfirst((string) ($entry->description ?: $entry->event ?: 'Change')), null],
        };

        return [
            'action' => $action,
            'detail' => $detail,
            'subject' => self::subjectLabel($entry->subject),
        ];
    }

    /**
     * The command center's audit strip: the latest work on one kind of
     * request — citizen or internal — newest first.
     *
     * Sign-ins are left out (the admin's own logins filled the strip); they
     * stay on the full Audit Log page.
     *
     * @return Collection<int, array{at: Carbon, user: string, role: string, action: string, subject: ?string, detail: ?string}>
     */
    public static function recentOnRequests(bool $internal, int $limit = 25, ?int $departmentId = null): Collection
    {
        $query = Activity::with(['causer.roles', 'subject'])
            ->whereHasMorph('subject', [Document::class], fn (Builder $documents) => match (true) {
                $internal && $departmentId !== null => $documents->involvingOffice($departmentId),
                $internal => $documents->internal(),
                $departmentId !== null => $documents->citizenFacing()->where('department_id', $departmentId),
                default => $documents->citizenFacing(),
            })
            // Several entries can share a second (a create and the first status
            // change, say), so the id breaks the tie — otherwise "newest first"
            // is whatever order the driver happens to return.
            ->latest()
            ->latest('id')
            ->take($limit);

        return self::withoutEmptyUpdates($query)
            ->get()
            ->map(function (Activity $entry): array {
                $role = $entry->causer?->roles->first()?->name;

                return [
                    'at' => $entry->created_at,
                    'user' => $entry->causer?->name ?? 'System',
                    'role' => $role ? str_replace('_', ' ', ucwords($role, '_')) : 'System',
                ] + self::describe($entry);
            });
    }

    /** What the row is about, as a person would name it. */
    public static function subjectLabel(?Model $subject): ?string
    {
        return match (true) {
            $subject === null, $subject instanceof User => null,
            $subject instanceof Document => $subject->tracking_number,
            default => class_basename($subject).($subject->getAttribute('name') ? ': '.$subject->getAttribute('name') : ''),
        };
    }

    /**
     * Leave out model saves that changed nothing worth logging. Documents no
     * longer write them (dontSubmitEmptyLogs), but older rows remain.
     */
    public static function withoutEmptyUpdates(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('event')
            ->orWhere('event', '!=', 'updated')
            // MariaDB normalises stored JSON (a space after each colon, keys
            // reordered) while SQLite keeps it as written, so match both.
            ->orWhere(fn (Builder $qq) => $qq
                ->where('properties', 'not like', '%"attributes":[]%')
                ->where('properties', 'not like', '%"attributes": []%')
                ->where('properties', 'not like', '%"attributes":{}%')
                ->where('properties', 'not like', '%"attributes": {}%')));
    }
}
