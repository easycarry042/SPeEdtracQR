<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Resource extends Model
{
    /** One of a thing — the gymnasium. Two approved reservations may not overlap. */
    public const KIND_FACILITY = 'facility';

    /** A countable pile — chairs, tents. Overlapping borrowings share the stock. */
    public const KIND_EQUIPMENT = 'equipment';

    protected $fillable = [
        'name',
        'kind',
        'description',
        'is_active',
        'sort_order',
        'total_quantity',
        'damaged_quantity',
        'capacity',
        'location',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'capacity' => 'integer',
    ];

    public function isEquipment(): bool
    {
        return $this->kind === self::KIND_EQUIPMENT;
    }

    public function isFacility(): bool
    {
        return ! $this->isEquipment();
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEquipment($query)
    {
        return $query->where('kind', self::KIND_EQUIPMENT);
    }

    public function scopeFacilities($query)
    {
        return $query->where('kind', '!=', self::KIND_EQUIPMENT);
    }

    /* ── Inventory ────────────────────────────────────────────────────────── */

    /**
     * Units that can be lent at all: what the office owns, less what is broken.
     * The ceiling no booking can exceed no matter when it falls.
     */
    public function serviceableUnits(): int
    {
        return max(0, (int) $this->total_quantity - (int) $this->damaged_quantity);
    }

    /**
     * Units already promised to, or out with, approved borrowings during the
     * window (Booking::unitsHeldDuring). Only approved bookings count — a
     * pending request has not been granted and must not reserve stock against
     * itself — and an overdue loan counts against every window until someone
     * records its return.
     */
    public function unitsCommitted(CarbonInterface $starts, CarbonInterface $ends, ?int $ignoreBookingId = null): int
    {
        $now = now();

        return (int) $this->bookings()
            ->open()
            ->where(fn ($q) => $q->whereNotNull('released_at')
                ->orWhere(fn ($inner) => $inner->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)))
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->with('document')
            ->get()
            ->sum(fn (Booking $booking): int => $booking->unitsHeldDuring($starts, $ends, $now));
    }

    /** Units free to lend across the window. */
    public function unitsAvailable(CarbonInterface $starts, CarbonInterface $ends, ?int $ignoreBookingId = null): int
    {
        return max(0, $this->serviceableUnits() - $this->unitsCommitted($starts, $ends, $ignoreBookingId));
    }

    /** Units handed over and not back yet, overdue ones included. */
    public function unitsOnLoan(): int
    {
        return (int) $this->bookings()->open()->whereNotNull('released_at')->with('document')->get()
            ->sum(fn (Booking $booking): int => $booking->unitsOutstanding());
    }

    /**
     * Units promised to approved borrowings still to be collected, whatever
     * their date. A missed pickup (window over, never collected) holds nothing.
     */
    public function unitsReserved(): int
    {
        return (int) $this->bookings()->open()->whereNull('released_at')->where('ends_at', '>', now())
            ->with('document')->get()
            ->sum(fn (Booking $booking): int => $booking->units());
    }

    /** In the stockroom right now: serviceable, less what is out on loan. */
    public function unitsOnHand(): int
    {
        return max(0, $this->serviceableUnits() - $this->unitsOnLoan());
    }

    /**
     * The approved booking holding a facility at this moment, if any — what
     * the counter needs to answer "is the gym free right now?".
     */
    public function bookingAt(CarbonInterface $at): ?Booking
    {
        return $this->bookings()
            ->where('status', Booking::STATUS_APPROVED)
            // Marked done early: the venue is free again.
            ->whereNull('returned_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->with('document')
            ->first();
    }

    /** The next approved booking starting after the given moment. */
    public function nextBookingAfter(CarbonInterface $at): ?Booking
    {
        return $this->bookings()
            ->where('status', Booking::STATUS_APPROVED)
            ->whereNull('returned_at')
            ->where('starts_at', '>', $at)
            ->orderBy('starts_at')
            ->with('document')
            ->first();
    }

    /**
     * Non-cancelled bookings on this resource that overlap the given window.
     * Overlap: existing.starts_at < ends AND existing.ends_at > starts.
     *
     * @return Collection<int, Booking>
     */
    public function conflicts(CarbonInterface $starts, CarbonInterface $ends, ?int $ignoreBookingId = null): Collection
    {
        return $this->bookings()
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            // A reservation marked done early no longer holds the rest of its slot.
            ->where(fn ($q) => $q->whereNull('returned_at')->orWhere('returned_at', '>', $starts))
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->get();
    }

    /**
     * Approved bookings overlapping the window — the set that blocks approving
     * or rescheduling another booking (two approved bookings can't overlap).
     *
     * @return Collection<int, Booking>
     */
    public function approvedConflicts(CarbonInterface $starts, CarbonInterface $ends, ?int $ignoreBookingId = null): Collection
    {
        return $this->bookings()
            ->where('status', Booking::STATUS_APPROVED)
            ->where('starts_at', '<', $ends)
            ->where('ends_at', '>', $starts)
            // A reservation marked done early no longer holds the rest of its slot.
            ->where(fn ($q) => $q->whereNull('returned_at')->orWhere('returned_at', '>', $starts))
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->get();
    }
}
