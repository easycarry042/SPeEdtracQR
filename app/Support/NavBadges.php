<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Booking;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;

/**
 * The counts on the sidebar's Dashboard and Internal rows: how many requests
 * have come to this person and are waiting on them. A count of work waiting,
 * not of things unseen — it falls when the work is picked up, not when the
 * page is opened.
 *
 * - Dashboard: a supervisor's unassigned citizen requests (their office's, plus
 *   any that reached no office — the same queue their dashboard triages); a
 *   staff member's new assignments still to accept.
 * - Internal: a supervisor's internal requests now at their office; a staff
 *   member's soft copies sent to them, plus paper folders at their office that
 *   nobody has scanned in yet. Both also count requests returned to them for
 *   revision (the supervisor: any their office filed; staff: their own).
 * - Resources: borrowing/reservation requests waiting for approval, plus loans
 *   past their return date that nobody has marked returned.
 *
 * Super admins get none: they oversee, they are not sent requests.
 */
final class NavBadges
{
    /** @return array{home: int, internal: int, resources: int} */
    public static function for(?User $user): array
    {
        if (! $user || $user->can('manage system')) {
            return ['home' => 0, 'internal' => 0, 'resources' => 0];
        }

        return [
            'home' => self::home($user),
            'internal' => self::internal($user),
            'resources' => self::resources($user),
        ];
    }

    /** The lending desk's To approve and Overdue queues (InventoryController::desk). */
    private static function resources(User $user): int
    {
        if (! $user->can('manage inventory')) {
            return 0;
        }

        $toApprove = Booking::query()
            ->where('status', Booking::STATUS_PENDING)
            ->where('ends_at', '>', now())
            // Only what can be approved now: the request is assigned and accepted.
            ->whereHas('document', fn ($q) => $q->whereIn('status', DocumentStatus::activeValues())
                ->whereNotNull('assigned_to')->whereNotNull('accepted_at'))
            ->whereHas('resource')
            ->count();

        $overdue = Booking::query()
            ->open()
            ->whereNotNull('released_at')
            ->where('ends_at', '<', now())
            ->whereHas('resource', fn ($q) => $q->equipment())
            ->count();

        return $toApprove + $overdue;
    }

    private static function home(User $user): int
    {
        if ($user->hasRole('Supervisor')) {
            return Document::query()
                ->citizenFacing()
                ->where('status', DocumentStatus::Pending->value)
                ->whereNull('assigned_to')
                ->when($user->department_id, fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('department_id', $user->department_id)
                    ->orWhereNull('department_id')))
                ->count();
        }

        // RequestReview::needsTriage(): assigned to them, not yet accepted.
        return Document::query()
            ->citizenFacing()
            ->where('assigned_to', $user->id)
            ->whereNull('accepted_at')
            ->whereIn('status', [DocumentStatus::Pending->value, DocumentStatus::InProgress->value])
            ->count();
    }

    private static function internal(User $user): int
    {
        if ($user->department_id === null) {
            return 0;
        }

        $atMyOffice = Document::query()
            ->internal()
            ->whereIn('status', DocumentStatus::activeValues())
            ->whereHas('requestSteps', fn ($q) => $q
                ->where('status', RequestStep::STATUS_CURRENT)
                ->where('department_id', $user->department_id));

        // Returned for revision: waiting on the office that filed it.
        $returnedToMe = Document::query()
            ->internal()
            ->where('status', DocumentStatus::Returned->value)
            ->when(
                $user->can('act on internal requests'),
                fn ($q) => $q->where('requesting_department_id', $user->department_id),
                fn ($q) => $q->where('created_by', $user->id),
            )
            ->count();

        if ($user->can('act on internal requests')) {
            return $atMyOffice->count() + $returnedToMe;
        }

        return $returnedToMe + $atMyOffice->get()
            ->filter(function (Document $document) use ($user): bool {
                $step = $document->currentRequestStep();

                return (int) $step?->recipient_id === (int) $user->id
                    || (! $document->isSoftCopy() && ! $document->currentStepHasCustody());
            })
            ->count();
    }
}
