<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Resource extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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
