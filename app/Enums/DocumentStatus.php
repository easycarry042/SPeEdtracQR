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
    case UnderReview = 'under_review';
    case PendingClearance = 'pending_clearance';
    case Approved = 'approved';
    case Completed = 'completed';
    case Returned = 'returned'; // side state — off the linear flow
    case OnHold = 'on_hold';    // side state — blocked/waiting; SLA paused
    case Denied = 'denied';     // terminal — supervisor rejected the request
    case Withdrawn = 'withdrawn'; // terminal — the filing office closed a returned internal request

    /** EDIT LABELS HERE. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'Ongoing',
            self::InReview, self::UnderReview, self::PendingClearance => 'Pending Approval',
            self::Approved => 'Approved',
            self::Completed => 'Ready for Claim',
            self::Returned => 'Returned / For Revision',
            self::OnHold => 'On Hold',
            self::Denied => 'Denied',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** Plain-language, citizen-facing explanation of what this status means. */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'We received your request and it is waiting to be processed.',
            self::InProgress => 'A staff member has claimed your request and is actively processing it.',
            self::InReview, self::UnderReview, self::PendingClearance => 'Staff has completed processing and submitted your request for supervisor review and clearance.',
            self::Approved => 'Your request has been approved and is being finalized.',
            self::Completed => 'Your request is complete and ready for claiming. Please check the claiming instructions below.',
            self::Returned => 'Something needs to be corrected. Please check for a message from staff.',
            self::OnHold => 'Your request is paused, usually waiting on a requirement. Staff will resume it.',
            self::Denied => 'Your request could not be approved. Staff should have included a reason.',
            self::Withdrawn => 'The office that filed this request closed it.',
        };
    }

    /**
     * The four stages the staff progress bar shows (Pending, Ongoing, Pending Approval, Ready for Claim),
     * each with the statuses that sit on it.
     *
     * Side states are placed separately — see railStage().
     *
     * @return array<int, array{label: string, statuses: array<int, string>}>
     */
    public static function railStages(): array
    {
        return [
            ['label' => 'Pending', 'statuses' => [self::Pending->value]],
            ['label' => 'Ongoing', 'statuses' => [self::InProgress->value]],
            ['label' => 'Pending Approval', 'statuses' => [self::InReview->value, self::UnderReview->value, self::PendingClearance->value]],
            ['label' => 'Ready for Claim', 'statuses' => [self::Completed->value, self::Approved->value]],
        ];
    }

    /**
     * 1-based stage on the four-stage bar. Off-line states show where the
     * request stands: Returned is back in review; On Hold and Denied sit where
     * the request was when it stopped (`$before`, if recorded).
     */
    public function railStage(?self $before = null): int
    {
        $onRail = match ($this) {
            self::Returned => self::InReview,
            self::OnHold, self::Denied, self::Withdrawn => $before && $before->railStageOrNull() ? $before : self::InReview,
            default => $this,
        };

        return $onRail->railStageOrNull() ?? 1;
    }

    private function railStageOrNull(): ?int
    {
        foreach (self::railStages() as $index => $stage) {
            if (in_array($this->value, $stage['statuses'], true)) {
                return $index + 1;
            }
        }

        return null;
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
        return $this === self::Completed || $this === self::Denied || $this === self::Withdrawn;
    }

    /**
     * The stages a citizen request can be in: Withdrawn belongs to internal
     * requests only, so citizen pages and citizen reports leave it out.
     *
     * @return array<int, self>
     */
    public static function citizenCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s): bool => $s !== self::Withdrawn));
    }

    /** All stored status values. */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** Open/active stages (everything except the terminal ones) — used for "in progress" counts. */
    public static function activeValues(): array
    {
        $terminal = [self::Completed->value, self::Denied->value, self::Withdrawn->value];

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
            self::InReview, self::UnderReview, self::PendingClearance => 24,
            self::Approved => 24,
            // Terminal + off-line side states have no SLA. On Hold PAUSES the
            // clock — a null budget means isOverdue() is always false and the
            // hourly CheckDocumentSla sweep skips it.
            self::Completed, self::Returned, self::OnHold, self::Denied, self::Withdrawn => null,
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
            self::InProgress, self::InReview, self::UnderReview, self::PendingClearance => 'brass',
            self::Returned, self::Denied => 'warn',
            self::OnHold => 'hold',
            self::Pending, self::Withdrawn => 'muted',
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

        if ($value === 'under_review' || $value === 'pending_clearance') {
            return self::InReview;
        }

        return self::tryFrom($value) ?? $default;
    }
}
