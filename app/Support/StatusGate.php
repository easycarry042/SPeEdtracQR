<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Validation\ValidationException;

/**
 * Stage gates: the requirements a document must meet before it may enter a
 * given status stage. The Blade stepper renders checks() as a live checklist
 * and the controller enforces validate() — one definition, so the UI and the
 * server can never disagree.
 *
 * Gates are HARD: a failed requirement blocks the transition server-side.
 */
class StatusGate
{
    /**
     * Requirement checklist for entering $to.
     * Each item: ['key' => string, 'label' => string, 'passed' => bool].
     *
     * A note submitted WITH the transition counts as work evidence for
     * →In Review — the controller persists it as a staff comment, so the
     * record stays consistent with what the gate accepted.
     */
    public static function checks(Document $document, DocumentStatus $to, ?string $note = null): array
    {
        return match ($to) {
            DocumentStatus::InProgress => [
                [
                    'key' => 'assigned',
                    'label' => 'A staff member is assigned',
                    'passed' => $document->assigned_to !== null,
                ],
                [
                    'key' => 'accepted',
                    'label' => 'The assignee accepted the request',
                    'passed' => $document->accepted_at !== null,
                ],
            ],
            DocumentStatus::InReview => [
                [
                    'key' => 'work_evidence',
                    'label' => 'An attachment or work note on file',
                    'passed' => trim((string) $note) !== ''
                        || $document->attachments()->exists()
                        || $document->comments()->where('author_type', 'staff')->exists(),
                ],
            ],
            // Before a request is approved, staff must have verified every
            // mandatory supporting requirement (Cedula, clearances, …) against
            // the originals. Requests without requirements pass automatically.
            DocumentStatus::Approved => [
                [
                    'key' => 'requirements_verified',
                    'label' => 'All required documents verified',
                    'passed' => $document->unverifiedMandatoryRequirements()->isEmpty(),
                ],
            ],
            default => [],
        };
    }

    /** Stages whose transition must carry a note (the "why" for the audit trail). */
    public static function noteRequired(DocumentStatus $to): bool
    {
        return $to === DocumentStatus::Approved;
    }

    /** Throws a ValidationException listing every unmet requirement. */
    public static function validate(Document $document, DocumentStatus $to, ?string $note = null): void
    {
        $failed = array_filter(self::checks($document, $to, $note), fn (array $c): bool => ! $c['passed']);

        $messages = array_map(
            fn (array $c): string => "Requirement not met: {$c['label']}.",
            array_values($failed),
        );

        if (self::noteRequired($to) && trim((string) $note) === '') {
            $messages[] = 'A review note is required to move a document to '.$to->label().'.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['status' => $messages]);
        }
    }

    /**
     * Optimistic concurrency guard: the form/JSON caller sends the stage it was
     * looking at; if the document has moved since, refuse with a clear message
     * instead of double-jumping.
     */
    public static function assertExpectedStatus(Document $document, ?string $expected): void
    {
        if ($expected === null || $expected === $document->status) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'This document was just updated by someone else — it is now "'
                .$document->statusEnum()->label().'". Review the new stage before making changes.',
        ]);
    }
}
