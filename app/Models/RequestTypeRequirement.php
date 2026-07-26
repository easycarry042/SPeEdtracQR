<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestTypeRequirement extends Model
{
    protected $fillable = [
        'request_type_id',
        'label',
        'is_mandatory',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }
}
