<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AssignmentScope
{
    public static function canViewAll(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('manage system')
            || $user->hasRole('super_admin')
            || $user->hasRole('Supervisor')
            || $user->hasRole('department_admin');
    }

    public static function canViewUnclaimedQueue(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return self::canViewAll($user)
            || $user->hasRole('receiving_staff')
            || $user->can('accept documents');
    }

    public static function applyDocumentScope(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (self::canViewAll($user)) {
            return $query;
        }

        if ($user->hasRole('receiving_staff')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereNull('assigned_to');
            });
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    public static function applyScanScope(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (self::canViewAll($user)) {
            return $query;
        }

        return $query->whereHas('document', function (Builder $doc) use ($user) {
            self::applyDocumentScope($doc, $user);
        });
    }

    public static function userCanAccessDocument(Document $document, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        if (self::canViewAll($user)) {
            return true;
        }

        if ((int) $document->created_by === (int) $user->id) {
            return true;
        }

        if ((int) $document->assigned_to === (int) $user->id) {
            return true;
        }

        return $document->assigned_to === null && self::canViewUnclaimedQueue($user);
    }
}
