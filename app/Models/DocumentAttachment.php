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

    /**
     * Authorized URL — access is checked per-department in AttachmentController.
     * Not named url() to avoid colliding with Eloquent's relationship resolution
     * when the attribute $attachment->url is read in views.
     */
    public function authorizedUrl(): string
    {
        return route('attachments.show', $this);
    }

    /**
     * Same access gate as authorizedUrl(), but forces a "Save as" download
     * instead of streaming the file inline.
     */
    public function downloadUrl(): string
    {
        return route('attachments.show', [$this, 'download' => 1]);
    }
}
