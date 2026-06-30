<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentComment extends Model
{
    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_PUBLIC = 'public';

    protected $fillable = [
        'document_id',
        'author_id',
        'author_type',
        'body',
        'visibility',
        'parent_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    /** Display name for the post's author, honouring the author_type. */
    public function authorLabel(): string
    {
        return match ($this->author_type) {
            'system' => 'System',
            'citizen' => $this->document?->citizen_name ?? 'Citizen',
            default => $this->author?->name ?? 'Staff',
        };
    }
}
