<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RouteTemplateStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One hop of a route template: which office, what it must do there, and
 * (optionally) under which amount condition the hop applies at all.
 */
class RouteTemplateStep extends Model
{
    /** @use HasFactory<RouteTemplateStepFactory> */
    use HasFactory;

    /** Step applies only when the request carries a peso amount. */
    public const CONDITION_HAS_AMOUNT = 'has_amount';

    /** Step applies when the amount is below the bidding threshold (SVP path). */
    public const CONDITION_BELOW_THRESHOLD = 'below_threshold';

    /** Step applies when the amount is at or above the bidding threshold (public bidding path). */
    public const CONDITION_AT_LEAST_THRESHOLD = 'at_least_threshold';

    /** @var array<string, string> condition value => admin-facing label */
    public const CONDITIONS = [
        self::CONDITION_HAS_AMOUNT => 'Only if an amount is attached',
        self::CONDITION_BELOW_THRESHOLD => 'Amount below ₱2M (Small Value Procurement)',
        self::CONDITION_AT_LEAST_THRESHOLD => 'Amount ₱2M and above (Public Bidding)',
    ];

    protected $fillable = [
        'route_template_id',
        'step_order',
        'department_id',
        'action',
        'condition',
    ];

    public function routeTemplate()
    {
        return $this->belongsTo(RouteTemplate::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** Whether this hop belongs in the chain for a request of the given amount. */
    public function appliesToAmount(?float $amount): bool
    {
        return match ($this->condition) {
            self::CONDITION_HAS_AMOUNT => $amount !== null,
            self::CONDITION_BELOW_THRESHOLD => $amount !== null && $amount < RouteTemplate::BIDDING_THRESHOLD,
            self::CONDITION_AT_LEAST_THRESHOLD => $amount !== null && $amount >= RouteTemplate::BIDDING_THRESHOLD,
            default => true,
        };
    }
}
