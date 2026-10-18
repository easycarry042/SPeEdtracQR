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

        // Org-wide means org-wide: only a super admin reads every office's work.
        // canViewAll() is deliberately NOT used here — it answers "may this user
        // see documents they were not assigned", which a department head may,
        // and returning the unscoped query for them leaked every other office's
        // documents into their Look Up, History, Analytics and Services report.
        if ($user->can('manage system')) {
            return $query;
        }

        // A department head reads their own office — plus intake that has not
        // been routed anywhere yet, which is every triager's to pick up (see
        // DashboardController::deptScopeIncludingUnrouted for the same rule).
        // Columns are table-qualified throughout: callers join `users` (the
        // analytics staff ranking does), and `users` has a department_id of its
        // own, so a bare column name is ambiguous and the query dies.
        if ($user->hasRole('Supervisor')) {
            // A supervisor who heads no particular office is not scoped to one.
            // DashboardController already reads them as org-wide (its $deptId
            // is null for them); scoping them to nothing here would blank out
            // their History and Analytics instead.
            if (! $user->department_id) {
                return $query;
            }

            return $query->where(fn (Builder $q) => $q
                ->where('documents.department_id', $user->department_id)
                ->orWhereNull('documents.department_id'));
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('documents.assigned_to', $user->id)
                ->orWhere('documents.created_by', $user->id);
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
