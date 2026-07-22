<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One physical hand-off of the paper folder: user_id took custody at
 * created_at. The latest event per document answers "who holds the paper?".
 */
class DocumentCustodyEvent extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'capture_method',
        'override_reason',
        'note',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
