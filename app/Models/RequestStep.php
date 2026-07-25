<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequestStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One materialized hop in an internal request's endorsement chain: the office
 * it must pass through, the action required there, and how/when it was acted
 * on. Chains are resolved from a RouteTemplate at creation time.
 */
class RequestStep extends Model
{
    /** @use HasFactory<RequestStepFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CURRENT = 'current';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_DENIED = 'denied';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'document_id',
        'step_order',
        'department_id',
        'action',
        'status',
        'acted_by',
        'acted_at',
        'started_at',
        'remarks',
        'signature_path',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'started_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function actedBy()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
