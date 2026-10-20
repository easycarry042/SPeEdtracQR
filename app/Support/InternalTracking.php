<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\RequestStep;

/**
 * What anyone holding an internal request's QR or tracking number may see,
 * without signing in: where it stands, which office has it, who there is
 * handling it, and the offices it has been through.
 *
 * The client asked for "who is handling it and which department". Nothing
 * else leaves the building: not what is being requested, not remarks, not the
 * files — a tracking number is printed on a folder anyone can pick up.
 */
final class InternalTracking
{
    /**
     * @return array{shows_names: bool, tracking: string, status: string, band: string, filed_by: ?string, filed_on: string, now: array{office: ?string, person: ?string, note: string}, steps: list<array{office: ?string, code: ?string, state: string, when: ?string, current: bool}>}
     */
    public static function summary(Document $document): array
    {
        $document->loadMissing(['requestSteps.department', 'requestSteps.recipient', 'requestingDepartment']);

        // Names are the client's ask; config('tracking.public_internal_tracker')
        // turns them off for offices-only, should legal say so.
        $showsNames = (bool) config('tracking.public_internal_tracker.show_names', true);
        $now = self::now($document);
        if (! $showsNames) {
            $now['person'] = null;
            $now['note'] = str_replace('Sent to them', 'Sent to someone there', $now['note']);
        }

        return [
            'shows_names' => $showsNames,
            'tracking' => $document->tracking_number,
            'status' => $document->internalStatusLabel(),
            'band' => $document->internalStatusBand(),
            'filed_by' => $document->requestingDepartment?->name,
            'filed_on' => $document->created_at->format('M j, Y g:i A'),
            'now' => $now,
            'steps' => $document->requestSteps
                ->sortBy([['step_order', 'asc'], ['id', 'asc']])
                ->map(fn (RequestStep $step): array => [
                    'office' => $step->department?->name,
                    'code' => $step->department?->code,
                    'state' => match ($step->status) {
                        RequestStep::STATUS_CURRENT => 'With this office now',
                        RequestStep::STATUS_APPROVED => 'Approved and passed on',
                        RequestStep::STATUS_FORWARDED => 'Handled and passed on',
                        RequestStep::STATUS_RETURNED => 'Returned for revision',
                        RequestStep::STATUS_DENIED => 'Denied',
                        default => 'Not reached',
                    },
                    'when' => ($step->acted_at ?? $step->started_at)?->format('M j, g:i A'),
                    'current' => $step->status === RequestStep::STATUS_CURRENT,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Who has it right now, in words.
     *
     * @return array{office: ?string, person: ?string, note: string}
     */
    private static function now(Document $document): array
    {
        $stage = $document->statusEnum();

        if ($stage === DocumentStatus::Completed) {
            return ['office' => null, 'person' => null, 'note' => 'Completed'.($document->completed_at ? ' on '.$document->completed_at->format('M j, Y') : '').'.'];
        }

        if ($stage === DocumentStatus::Denied) {
            return ['office' => null, 'person' => null, 'note' => 'This request was denied.'];
        }

        if ($stage === DocumentStatus::Withdrawn) {
            return ['office' => null, 'person' => null, 'note' => 'The office that filed it withdrew this request.'];
        }

        if ($stage === DocumentStatus::Returned) {
            return [
                'office' => $document->requestingDepartment?->name,
                'person' => $document->creator?->name,
                'note' => 'Returned to the office that filed it, for revision.',
            ];
        }

        $step = $document->currentRequestStep();
        if (! $step) {
            return ['office' => null, 'person' => null, 'note' => 'Not with any office right now.'];
        }

        $office = $step->department?->name;

        if ($document->currentStepHasCustody()) {
            $holder = $document->currentCustody();

            return [
                'office' => $office,
                'person' => $holder?->user?->name,
                'note' => 'Received '.$holder?->created_at?->format('M j, g:i A').'.',
            ];
        }

        if ($step->recipient) {
            return [
                'office' => $office,
                'person' => $step->recipient->name,
                'note' => 'Sent to them — not received yet.',
            ];
        }

        return [
            'office' => $office,
            'person' => null,
            'note' => 'On its way — nobody at the office has received it yet.',
        ];
    }
}
