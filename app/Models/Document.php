<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'tracking_number',
        'document_type',
        'citizen_name',
        'citizen_contact',
        'citizen_email',
        'source',
        'notify_citizen',
        'description',
        'purpose',
        'status',
        'created_by',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'status_changed_at',
        'remarks',
        'qr_code_path',
        'attachment_path',
        'received_at',
        'completed_at',
        'sla_warning_notified_at',
        'sla_breach_notified_at',
        'sla_breached_at',
        'status_before_hold',
        'hold_reason',
        'hold_until',
        'blocked_by',
        'held_at',
        'held_by',
    ];

    /** Allowed values for the `blocked_by` hold tag. */
    public const BLOCKED_BY = ['citizen', 'internal', 'external'];

    protected $casts = [
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'sla_warning_notified_at' => 'datetime',
        'sla_breach_notified_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'notify_citizen' => 'boolean',
        'hold_until' => 'date',
        'held_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tracking_number', 'status', 'assigned_to',
                'citizen_name', 'citizen_contact', 'document_type', 'purpose', 'description', 'remarks',
                'hold_reason', 'hold_until', 'blocked_by', 'status_before_hold',
            ])
            ->logOnlyDirty();
    }

    /**
     * The current stage as a DocumentStatus enum. `status` is stored as a string
     * (varchar) so legacy string comparisons keep working; the enum is the
     * authority for labels, ordering and SLA. Tolerates the legacy `in_transit`.
     */
    public function statusEnum(): DocumentStatus
    {
        return DocumentStatus::fromLoose($this->status);
    }

    /**
     * Apply a stage change to the model (does NOT save). Callers are responsible
     * for permission checks, persistence, activity logging and broadcasting —
     * see DocumentStatusController.
     */
    public function applyStatus(DocumentStatus $status): void
    {
        $this->status = $status->value;
        $this->status_changed_at = now();

        // New stage = fresh SLA stay: restart the clock and let the scheduled
        // sweep (documents:check-sla) re-notify if it overstays this stage.
        $this->sla_warning_notified_at = null;
        $this->sla_breach_notified_at = null;

        // Keep the legacy completion timestamp coherent for analytics/history.
        $this->completed_at = $status->isTerminal() ? now() : null;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Staff member responsible for advancing this document through its stages. */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Admin who made the current assignment. */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Staff member / admin who placed the document on hold. */
    public function heldBy()
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class)->orderBy('sort_order');
    }

    /** Top-level collaboration posts (replies are loaded via the `replies` relation). */
    public function comments()
    {
        return $this->hasMany(DocumentComment::class)->whereNull('parent_id')->latest();
    }

    /** Citizen-facing posts only — never exposes internal staff notes. */
    public function publicComments()
    {
        return $this->comments()->where('visibility', DocumentComment::VISIBILITY_PUBLIC);
    }

    /**
     * Record a system event (status change, assignment, return) into the unified
     * per-document feed as an internal, author-less note.
     */
    public function logSystemComment(string $body): DocumentComment
    {
        return $this->comments()->create([
            'author_id' => null,
            'author_type' => 'system',
            'body' => $body,
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);
    }

    // Backward-compatible helper. Primary generation lives in QrCodeService.
    public static function generateTrackingNumber()
    {
        return 'SPD-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    /**
     * Overdue = sitting in its current stage longer than the stage's SLA budget.
     * Re-anchored to status_changed_at (manual model); terminal/off-line stages
     * and stages with no SLA budget are never overdue.
     */
    public function isOverdue(): bool
    {
        $stage = $this->statusEnum();
        $sla = $stage->slaHours();

        if ($sla === null || $stage->isTerminal()) {
            return false;
        }

        $anchor = $this->status_changed_at ?? $this->updated_at ?? $this->created_at;

        if (! $anchor) {
            return false;
        }

        // anchor is in the past; order operands so the diff is positive.
        return $anchor->diffInHours(now()) > $sla;
    }
}
