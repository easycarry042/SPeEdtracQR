<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DepartmentScope
{
    public static function isOrgWide(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->can('manage system') ?? false;
    }

    public static function departmentId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user || self::isOrgWide($user)) {
            return null;
        }

        return $user->department_id;
    }

    public static function canViewIntakeQueue(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('receiving_staff')) {
            return true;
        }

        $name = $user->department?->name ?? '';

        return str_contains(strtolower($name), 'reception')
            || str_contains(strtolower($name), 'front desk');
    }

    /**
     * Documents relevant to the user's department (current location, past scans, or intake queue).
     */
    public static function applyDocumentScope(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $deptId = self::departmentId($user);

        if (! $deptId) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($deptId, $user) {
            $q->where('current_department_id', $deptId)
                ->orWhereHas('scans', fn (Builder $s) => $s->where('department_id', $deptId))
                ->orWhereHas('routeSteps', fn (Builder $r) => $r->where('department_id', $deptId));

            if (self::canViewIntakeQueue($user)) {
                $q->orWhere(function (Builder $pending) {
                    $pending->where('status', 'pending')
                        ->whereNull('current_department_id');
                });
            }
        });
    }

    /**
     * Documents physically at the user's department right now.
     */
    public static function applyCurrentDepartmentScope(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $deptId = self::departmentId($user);

        if (! $deptId) {
            return $query;
        }

        return $query->where('current_department_id', $deptId);
    }

    public static function applyScanScope(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $deptId = self::departmentId($user);

        if (! $deptId) {
            return $query;
        }

        return $query->where('department_id', $deptId);
    }

    public static function userCanAccessDocument(Document $document, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || self::isOrgWide($user)) {
            return true;
        }

        $deptId = self::departmentId($user);

        if (! $deptId) {
            return true;
        }

        if ((int) $document->current_department_id === $deptId) {
            return true;
        }

        if ($document->scans()->where('department_id', $deptId)->exists()) {
            return true;
        }

        if ($document->isOnRoutingPath($deptId)) {
            return true;
        }

        if (self::canViewIntakeQueue($user)
            && $document->status === 'pending'
            && $document->current_department_id === null) {
            return true;
        }

        return false;
    }
}
