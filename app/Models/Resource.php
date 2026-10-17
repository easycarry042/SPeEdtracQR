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
     * Units already promised to approved borrowings overlapping the window.
     *
     * The borrowed quantity lives on the document, not the booking, so this
     * reads through the join. Only approved bookings count — a pending request
     * has not been granted yet and must not reserve stock against itself.
     */
    public function unitsCommitted(CarbonInterface $starts, CarbonInterface $ends, ?int $ignoreBookingId = null): int
    {
        return (int) $this->bookings()
            ->where('bookings.status', Booking::STATUS_APPROVED)
            ->where('bookings.starts_at', '<', $ends)
            ->where('bookings.ends_at', '>', $starts)
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->join('documents', 'documents.id', '=', 'bookings.document_id')
            // A deleted request is not holding anything.
            ->whereNull('documents.deleted_at')
            ->sum('documents.quantity');
    }

    /** Units free to lend across the window. */
    public function unitsAvailable(CarbonInterface $starts, CarbonInterface $ends, ?int $ignoreBookingId = null): int
    {
        return max(0, $this->serviceableUnits() - $this->unitsCommitted($starts, $ends, $ignoreBookingId));
    }

    /**
     * The approved booking holding a facility at this moment, if any — what
     * the counter needs to answer "is the gym free right now?".
     */
    public function bookingAt(CarbonInterface $at): ?Booking
    {
        return $this->bookings()
            ->where('status', Booking::STATUS_APPROVED)
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
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->get();
    }
}
