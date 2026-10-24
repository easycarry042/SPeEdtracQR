<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reservation of a resource for a window, tied to a request (document).
 *
 * For equipment it is also a loan, which runs: pending → approved (units
 * promised, `quantity` set by staff) → handed over (`released_at`) → returned
 * (`returned_at`, once every unit is back or written off). A loan is only
 * closed when someone records the return; past its return date it is overdue
 * and keeps its units out of stock until then.
 */
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    /** Where a loan stands at the counter (lendingState()). */
    public const LENDING_PENDING = 'pending';

    public const LENDING_RESERVED = 'reserved';

    public const LENDING_MISSED = 'missed';

    public const LENDING_ON_LOAN = 'on_loan';

    public const LENDING_OVERDUE = 'overdue';

    public const LENDING_RETURNED = 'returned';

    public const LENDING_CANCELLED = 'cancelled';

    protected $fillable = [
        'document_id',
        'resource_id',
        'quantity',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'released_at',
        'released_by',
        'returned_at',
        'returned_by',
        'returned_quantity',
        'damaged_quantity',
        'lost_quantity',
        'return_notes',
        'overdue_reminded_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'released_at' => 'datetime',
        'returned_at' => 'datetime',
        'overdue_reminded_at' => 'datetime',
        'quantity' => 'integer',
        'returned_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'lost_quantity' => 'integer',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Approved and not yet closed — still promised, out, or overdue. A deleted
     * request holds nothing, so its document must exist.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_APPROVED)
            ->whereNull('returned_at')
            ->whereHas('document');
    }

    /* ── Lending ──────────────────────────────────────────────────────────── */

    /**
     * Units this booking is for: what staff granted, or the citizen's ask
     * while it is still pending (and for bookings made before staff set one).
     */
    public function units(): int
    {
        return (int) ($this->quantity ?? $this->document?->quantity ?? 0);
    }

    /** The citizen's ask, as filed on the request form. */
    public function unitsRequested(): int
    {
        return (int) ($this->document?->quantity ?? 0);
    }

    /** Units handed over and not yet back (or written off). */
    public function unitsOutstanding(): int
    {
        return max(0, $this->units() - (int) $this->returned_quantity - (int) $this->lost_quantity);
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /** Closed: everything is back or written off (a facility: marked done). */
    public function isClosed(): bool
    {
        return $this->returned_at !== null;
    }

    public function isOnLoan(): bool
    {
        return $this->isApproved() && $this->isReleased() && ! $this->isClosed();
    }

    /** Out past its return date and nobody has marked it back. */
    public function isOverdue(?CarbonInterface $at = null): bool
    {
        return $this->isOnLoan() && $this->ends_at->lt($at ?? now());
    }

    /** Whole days past the return date — 1 on the first day after it. */
    public function daysOverdue(?CarbonInterface $at = null): int
    {
        if (! $this->isOverdue($at)) {
            return 0;
        }

        return max(1, (int) ceil($this->ends_at->diffInHours($at ?? now()) / 24));
    }

    public function isAwaitingPickup(): bool
    {
        return $this->isApproved() && ! $this->isReleased() && ! $this->isClosed();
    }

    public function lendingState(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            $this->isCancelled() => self::LENDING_CANCELLED,
            ! $this->isApproved() => self::LENDING_PENDING,
            $this->isClosed() => self::LENDING_RETURNED,
            $this->isOverdue($at) => self::LENDING_OVERDUE,
            $this->isReleased() => self::LENDING_ON_LOAN,
            // Promised, never collected, and its window is over: it holds
            // nothing any more, but someone should cancel or hand it over.
            $this->ends_at->lte($at) => self::LENDING_MISSED,
            default => self::LENDING_RESERVED,
        };
    }

    /** Staff wording for lendingState(). */
    public function lendingLabel(?CarbonInterface $at = null): string
    {
        return match ($this->lendingState($at)) {
            self::LENDING_PENDING => 'Waiting for approval',
            self::LENDING_RESERVED => $this->resource?->isEquipment() ? 'Approved — ready for pickup' : 'Approved',
            self::LENDING_MISSED => 'Not picked up',
            self::LENDING_ON_LOAN => 'Handed over',
            self::LENDING_OVERDUE => 'Overdue',
            self::LENDING_RETURNED => $this->resource?->isEquipment() ? 'Returned' : 'Done',
            default => 'Cancelled',
        };
    }

    /**
     * Units this booking keeps from being lent to anyone else during a window.
     *
     * Promised but not collected: its own dates. Handed over: from the
     * hand-over until the return date — and once that date passes, until
     * someone records the return, however long that takes. Overdue chairs are
     * not in the stockroom, so they cannot be promised to the next borrower.
     */
    public function unitsHeldDuring(CarbonInterface $starts, CarbonInterface $ends, ?CarbonInterface $now = null): int
    {
        $now ??= now();

        if (! $this->isApproved() || $this->isClosed()) {
            return 0;
        }

        if ($this->isReleased()) {
            $from = $this->released_at->lt($this->starts_at) ? $this->released_at : $this->starts_at;
            $openEnded = $this->ends_at->lte($now);

            return $from->lt($ends) && ($openEnded || $this->ends_at->gt($starts))
                ? $this->unitsOutstanding()
                : 0;
        }

        return $this->starts_at->lt($ends) && $this->ends_at->gt($starts) ? $this->units() : 0;
    }
}
