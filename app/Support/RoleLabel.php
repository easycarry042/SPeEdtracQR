<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * One place that turns a stored role name into the words users read.
 *
 * The database keeps its historical role keys (`staff`, `Supervisor`,
 * `super_admin`) because permissions, middleware, and seeded data all depend on
 * them. The UI, however, must name the three entities consistently everywhere —
 * mixed "super_admin" / "Super Admin" / "Administrator" labels are exactly what
 * made the role system feel confusing.
 */
class RoleLabel
{
    /** Stored role key → the label shown in the interface. */
    private const LABELS = [
        'staff' => 'Staff',
        'Supervisor' => 'Supervisor',
        'super_admin' => 'Administrator',
    ];

    public static function for(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return self::LABELS[$role] ?? Str::headline($role);
    }

    /** The label for a user's primary (first) role. */
    public static function forUser(?User $user): ?string
    {
        return self::for($user?->getRoleNames()->first());
    }

    /**
     * Role keys with their labels, in privilege order — for filters and pickers.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }
}
