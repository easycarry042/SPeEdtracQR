<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestType extends Model
{
    public const KIND_DOCUMENT = 'document';

    public const KIND_BOOKING = 'booking';

    public const KIND_EQUIPMENT = 'equipment';

    /** Produce/deliver a quantity of something by a date (e.g. lei making). */
    public const KIND_SERVICE = 'service';

    /** Kinds that reserve a resource for a time window (facility + equipment). */
    public const RESOURCE_KINDS = [self::KIND_BOOKING, self::KIND_EQUIPMENT];

    protected $fillable = [
        'name',
        'kind',
        'department_id',
        'resource_id',
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

    /**
     * The department that handles this type of request. Tickets of this type are
     * routed to this department's queue on submission.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The resource a booking-kind type reserves (null for document types).
     *
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** Does this type reserve a resource (facility booking or equipment borrowing)? */
    public function usesResource(): bool
    {
        return in_array($this->kind, self::RESOURCE_KINDS, true);
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

    public function scopeEquipment($query)
    {
        return $query->where('kind', self::KIND_EQUIPMENT);
    }

    public function scopeServices($query)
    {
        return $query->where('kind', self::KIND_SERVICE);
    }
}
