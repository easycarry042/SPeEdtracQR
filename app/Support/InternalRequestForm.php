<?php

namespace App\Support;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What the internal-request filing form needs. The form opens in a modal from
 * the header on every staff page, and on its own page at /requests/create.
 */
class InternalRequestForm
{
    /** Filing needs the permission and an office to file from. */
    public static function canFile(?User $user): bool
    {
        return $user !== null
            && $user->can('create internal requests')
            && $user->department_id !== null;
    }

    /**
     * The departments a request can be routed to first. The chain is that
     * department behind the filer's own endorsement.
     *
     * The people who can receive a soft copy are fetched per office when one
     * is picked (requests.recipients): the form sits in a modal on every
     * staff page, so it does not carry the whole staff list.
     *
     * @return array{departments: Collection<int, Department>, departmentsJson: Collection<int, array{id: int, name: string, code: string}>}
     */
    public static function data(): array
    {
        $departments = Department::active()->orderBy('name')->get(['id', 'name', 'code']);

        return [
            'departments' => $departments,
            'departmentsJson' => $departments->map(fn (Department $d): array => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
            ])->values(),
        ];
    }

    /**
     * The people who can receive a soft-copy request at each office: its
     * active staff and supervisors, keyed by department id.
     *
     * @param  list<int>  $departmentIds
     * @return Collection<int|string, list<array{id: int, name: string, role: string}>>
     */
    public static function recipients(array $departmentIds): Collection
    {
        return User::query()
            ->with('roles:id,name')
            ->whereIn('department_id', $departmentIds)
            ->where('is_active', true)
            ->role(['staff', 'Supervisor'])
            ->orderBy('name')
            ->get(['id', 'name', 'department_id'])
            ->groupBy('department_id')
            ->map(fn (Collection $people): array => $people->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->hasRole('Supervisor') ? 'Supervisor' : 'Staff',
            ])->values()->all());
    }

    /** Whether this person may be named to receive a soft copy at that office. */
    public static function isRecipientAt(int $userId, int $departmentId): bool
    {
        return User::whereKey($userId)
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->role(['staff', 'Supervisor'])
            ->exists();
    }
}
