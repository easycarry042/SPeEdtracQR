<?php

namespace App\Enums;

/**
 * Single source of truth for a document's lifecycle.
 *
 * Documents are advanced MANUALLY by their assigned staff member (and admins)
 * through the linear flow() below. `Returned` is a side state (for revision),
 * not part of the forward line.
 *
 * To rename a stage: edit its label() entry — that's the only change needed.
 * To re-order / add / remove a linear stage: edit flow(). Adding a value needs
 * no DB migration (documents.status is a varchar).
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Completed = 'completed';
    case Returned = 'returned'; // side state — off the linear flow
    case OnHold = 'on_hold';    // side state — blocked/waiting; SLA paused
    case Denied = 'denied';     // terminal — supervisor rejected the request

    /** EDIT LABELS HERE. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::Completed => 'Completed',
            self::Returned => 'Returned / For Revision',
            self::OnHold => 'On Hold',
            self::Denied => 'Denied',
        };
    }

    /** Plain-language, citizen-facing explanation of what this status means. */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'We received your request and it is waiting to be picked up by staff.',
            self::InProgress => 'A staff member is actively working on your request.',
            self::InReview => 'Your request is being checked and reviewed before approval.',
            self::Approved => 'Your request has been approved and is being finalized.',
            self::Completed => 'Your document is finished and ready. Watch for pickup or release details.',
            self::Returned => 'Something needs to be corrected. Please check for a message from staff.',
            self::OnHold => 'Your request is paused, usually waiting on a requirement. Staff will resume it.',
            self::Denied => 'Your request could not be approved. Staff should have included a reason.',
        };
    }

    /** The ordered forward line shown in the stepper. Excludes Returned. */
    public static function flow(): array
    {
        return [
            self::Pending,
            self::InProgress,
            self::InReview,
            self::Approved,
            self::Completed,
        ];
    }

    /** Stages where the document is finished and the SLA clock stops. */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Denied;
    }

    /** All stored status values. */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** Open/active stages (everything except the terminal ones) — used for "in progress" counts. */
    public static function activeValues(): array
    {
        $terminal = [self::Completed->value, self::Denied->value];

        return array_values(array_filter(
            self::values(),
            fn (string $v): bool => ! in_array($v, $terminal, true),
        ));
    }

    /** 1-based position in the forward line (0 for the off-line Returned state). */
    public function position(): int
    {
        $index = array_search($this, self::flow(), true);

        return $index === false ? 0 : $index + 1;
    }

    /** Next stage in the forward line, or null at the end / off the line. */
    public function next(): ?self
    {
        $flow = self::flow();
        $index = array_search($this, $flow, true);

        if ($index === false || ! isset($flow[$index + 1])) {
            return null;
        }

        return $flow[$index + 1];
    }

    /** Previous stage in the forward line, or null at the start / off the line. */
    public function previous(): ?self
    {
        $flow = self::flow();
        $index = array_search($this, $flow, true);

        if ($index === false || $index === 0) {
            return null;
        }

        return $flow[$index - 1];
    }

    /**
     * Per-stage SLA budget in hours, anchored to status_changed_at. Falls back
     * to DEFAULT_SLA_HOURS. Terminal/off-line stages have no SLA (null).
     */
    public const DEFAULT_SLA_HOURS = 48;

    public function slaHours(): ?int
    {
        return match ($this) {
            self::Pending => 24,
            self::InProgress => 72,
            self::InReview => 48,
            self::Approved => 24,
            // Terminal + off-line side states have no SLA. On Hold PAUSES the
            // clock — a null budget means isOverdue() is always false and the
            // hourly CheckDocumentSla sweep skips it.
            self::Completed, self::Returned, self::OnHold, self::Denied => null,
        };
    }

    /**
     * Civic Record colour band for the stepper / badge.
     * green = done/active · brass = in progress/review · gray = not reached ·
     * warn = returned · hold = blocked/waiting (amber). The CSS classes live in
     * resources/css/app.css.
     */
    public function band(): string
    {
        return match ($this) {
            self::Completed, self::Approved => 'green',
            self::InProgress, self::InReview => 'brass',
            self::Returned, self::Denied => 'warn',
            self::OnHold => 'hold',
            self::Pending => 'muted',
        };
    }

    /** Tolerant parse: accepts enum, canonical key, or legacy `in_transit`. */
    public static function fromLoose(self|string|null $value, self $default = self::Pending): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if ($value === 'in_transit') {
            return self::InProgress;
        }

        return self::tryFrom($value) ?? $default;
    }
}
