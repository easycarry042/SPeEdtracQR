<?php

namespace App\Support;

use App\Enums\DocumentStatus;
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
 *   nobody has scanned in yet.
 *
 * Super admins get none: they oversee, they are not sent requests.
 */
final class NavBadges
{
    /** @return array{home: int, internal: int} */
    public static function for(?User $user): array
    {
        if (! $user || $user->can('manage system')) {
            return ['home' => 0, 'internal' => 0];
        }

        return [
            'home' => self::home($user),
            'internal' => self::internal($user),
        ];
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

        if ($user->can('act on internal requests')) {
            return $atMyOffice->count();
        }

        return $atMyOffice->get()
            ->filter(function (Document $document) use ($user): bool {
                $step = $document->currentRequestStep();

                return (int) $step?->recipient_id === (int) $user->id
                    || (! $document->isSoftCopy() && ! $document->currentStepHasCustody());
            })
            ->count();
    }
}
