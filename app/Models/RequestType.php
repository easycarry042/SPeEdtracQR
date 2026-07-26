<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestType extends Model
{
    public const KIND_DOCUMENT = 'document';

    public const KIND_BOOKING = 'booking';

    protected $fillable = [
        'name',
        'kind',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Requirement checklist for this type, in display order.
     *
     * @return HasMany<RequestTypeRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(RequestTypeRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDocuments($query)
    {
        return $query->where('kind', self::KIND_DOCUMENT);
    }

    public function scopeBookings($query)
    {
        return $query->where('kind', self::KIND_BOOKING);
    }
}
