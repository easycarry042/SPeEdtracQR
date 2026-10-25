<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestTypeHandler extends Model
{
    public $timestamps = false;

    protected $table = 'request_type_handlers';

    protected $fillable = [
        'request_type_id',
        'user_id',
        'sort_order',
    ];

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
