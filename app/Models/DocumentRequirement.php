<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    protected $fillable = [
        'document_id',
        'request_type_requirement_id',
        'label',
        'is_mandatory',
        'uploaded_file_path',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(RequestTypeRequirement::class, 'request_type_requirement_id');
    }
}
