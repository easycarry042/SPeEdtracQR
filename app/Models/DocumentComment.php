<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One message on a request's conversation.
 *
 * Two threads share this table, separated by `visibility`:
 *   - 'public'   — the citizen ↔ staff conversation, shown on the tracking page.
 *   - 'internal' — the staff-only thread; never leaves an authenticated context.
 *
 * `author_type` says who spoke (staff / citizen / system) and `parent_id` groups
 * a question with its answers (one level of nesting).
 */
class DocumentComment extends Model
{
    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITY_PUBLIC = 'public';

    public const AUTHOR_STAFF = 'staff';

    public const AUTHOR_CITIZEN = 'citizen';

    public const AUTHOR_SYSTEM = 'system';

    protected $fillable = [
        'document_id',
        'author_id',
        'author_type',
        'author_name',
        'body',
        'visibility',
        'parent_id',
        'staff_read_at',
        'citizen_read_at',
        'attachment_path',
        'attachment_name',
    ];

    protected $casts = [
        'staff_read_at' => 'datetime',
        'citizen_read_at' => 'datetime',
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

    public function isInternal(): bool
    {
        return $this->visibility === self::VISIBILITY_INTERNAL;
    }

    public function isFromCitizen(): bool
    {
        return $this->author_type === self::AUTHOR_CITIZEN;
    }

    public function isFromSystem(): bool
    {
        return $this->author_type === self::AUTHOR_SYSTEM;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /**
     * The citizen-visible thread: what the tracking page may render, and the only
     * thing a citizen-facing endpoint is ever allowed to return.
     *
     * @param  Builder<DocumentComment>  $query
     */
    public function scopeCitizenVisible(Builder $query): void
    {
        $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /**
     * Citizen messages staff have not read yet — the unread badge on a ticket.
     *
     * @param  Builder<DocumentComment>  $query
     */
    public function scopeUnreadByStaff(Builder $query): void
    {
        $query->where('author_type', self::AUTHOR_CITIZEN)->whereNull('staff_read_at');
    }

    /** Display name for the message's author, honouring the author_type. */
    public function authorLabel(): string
    {
        return match ($this->author_type) {
            self::AUTHOR_SYSTEM => 'System',
            self::AUTHOR_CITIZEN => $this->author_name ?: ($this->document?->citizen_name ?? 'Citizen'),
            default => $this->author?->name ?? 'Staff',
        };
    }

    /**
     * What the CITIZEN sees as the sender.
     *
     * Staff are named, the same way the tracking page already names the assignee
     * under "Handled by": a citizen is entitled to know who answered them, and
     * anonymising only the message byline would contradict the panel beside it.
     *
     * The stored author_name wins over the live relation so a byline keeps the
     * name it was posted under after a rename or deactivation; 'Staff' is the
     * last resort so a removed user never renders a blank sender.
     */
    public function publicAuthorLabel(): string
    {
        return match ($this->author_type) {
            self::AUTHOR_SYSTEM => 'Update',
            self::AUTHOR_CITIZEN => 'You',
            default => $this->author_name ?: ($this->author?->name ?? 'Staff'),
        };
    }
}
