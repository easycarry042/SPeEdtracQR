<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    /** Not yet reviewed (also the state a re-uploaded item returns to). */
    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_NEEDS_REVISION = 'needs_revision';

    public const REVIEW_REJECTED = 'rejected';

    /** Review outcomes a staff member may set. */
    public const REVIEW_STATUSES = [
        self::REVIEW_APPROVED,
        self::REVIEW_NEEDS_REVISION,
        self::REVIEW_REJECTED,
    ];

    protected $fillable = [
        'document_id',
        'request_type_requirement_id',
        'label',
        'is_mandatory',
        'uploaded_file_path',
        'verified_at',
        'verified_by',
        'notes',
        'review_status',
        'review_comment',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'verified_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function needsRevision(): bool
    {
        return $this->review_status === self::REVIEW_NEEDS_REVISION;
    }

    public function isRejected(): bool
    {
        return $this->review_status === self::REVIEW_REJECTED;
    }

    public function isApproved(): bool
    {
        return $this->review_status === self::REVIEW_APPROVED;
    }

    /** Human label for the current review status. */
    public function reviewStatusLabel(): string
    {
        return match ($this->review_status) {
            self::REVIEW_APPROVED => 'Approved',
            self::REVIEW_NEEDS_REVISION => 'Needs revision',
            self::REVIEW_REJECTED => 'Rejected',
            default => 'Pending review',
        };
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
