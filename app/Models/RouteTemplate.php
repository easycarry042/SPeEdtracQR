<?php

namespace App\Models;

use Database\Factories\RouteTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Admin-defined endorsement route for internal requests (e.g. "Procurement
 * Request": Mayor → Budget → BAC → GSO). Conditional steps let one template
 * branch on the request's peso amount — see RouteTemplateStep::CONDITIONS.
 */
class RouteTemplate extends Model
{
    /** @use HasFactory<RouteTemplateFactory> */
    use HasFactory;

    /**
     * RA 12009 line: at or above this amount procurement goes to public
     * bidding; below it, Small Value Procurement applies.
     */
    public const BIDDING_THRESHOLD = 2_000_000;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(RouteTemplateStep::class)->orderBy('step_order')->orderBy('id');
    }

    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve the concrete chain for a given request amount: keeps every
     * unconditional step, drops amount-dependent steps that don't apply.
     *
     * @return Collection<int, RouteTemplateStep>
     */
    public function stepsForAmount(?float $amount): Collection
    {
        return $this->steps->filter(fn (RouteTemplateStep $step): bool => $step->appliesToAmount($amount))->values();
    }
}
