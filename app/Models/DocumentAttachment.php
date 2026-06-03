<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends Model
{
    protected $fillable = [
        'document_id',
        'file_path',
        'uploaded_by',
        'department_id',
        'sort_order',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Authorized URL — access is checked per-department in AttachmentController.
     * Not named url() to avoid colliding with Eloquent's relationship resolution
     * when the attribute $attachment->url is read in views.
     */
    public function authorizedUrl(): string
    {
        return route('attachments.show', $this);
    }
}
