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
}
