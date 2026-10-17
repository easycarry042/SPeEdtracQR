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
            || $user->hasRole('Supervisor');
    }

    public static function canViewUnclaimedQueue(?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return self::canViewAll($user)
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

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    /**
     * Enforces the Guest → Supervisor → Staff flow: a department-head Supervisor
     * may only assign a ticket to staff in their OWN department. Super admins are
     * org-wide and may assign across departments.
     */
    public static function canAssignWithinDepartment(User $actor, User $assignee): bool
    {
        if ($actor->can('manage system')) {
            return true;
        }

        return $actor->department_id !== null
            && (int) $assignee->department_id === (int) $actor->department_id;
    }

    /**
     * Who may EDIT a document's details. Stricter than view access: supervisors
     * and admins may correct any record, but a staff member may only edit the
     * ticket currently assigned to them — never unclaimed or others' tickets.
     */
    public static function userCanEditDocument(Document $document, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        return self::canViewAll($user)
            || (int) $document->assigned_to === (int) $user->id;
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
