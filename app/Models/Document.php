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
        'current_department_id',
        'created_by',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'status_changed_at',
        'remarks',
        'qr_code_path',
        'attachment_path',
        'received_at',
        'completed_at',
        'sla_warning_notified_at',
        'sla_breach_notified_at',
        'sla_breached_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'sla_warning_notified_at' => 'datetime',
        'sla_breach_notified_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'notify_citizen' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tracking_number', 'status', 'assigned_to', 'current_department_id',
                'citizen_name', 'citizen_contact', 'document_type', 'purpose', 'description', 'remarks',
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

    public function scans()
    {
        return $this->hasMany(DocumentScan::class)->orderBy('scanned_at', 'desc');
    }

    public function currentDepartment()
    {
        return $this->belongsTo(Department::class, 'current_department_id');
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

    /**
     * @deprecated Routing-era (department chain). Advancement is now manual via
     * DocumentStatus stages; this relation is retained read-only for legacy data
     * and is no longer populated. Slated for removal with the route_steps table.
     */
    public function routeSteps()
    {
        return $this->hasMany(DocumentRouteStep::class)->orderBy('step_order');
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

    public function isAtLastRouteStop(): bool
    {
        $chain = $this->getRoutingChain();

        if ($chain->isEmpty()) {
            return true;
        }

        return (int) $this->current_department_id === (int) $chain->last()->id;
    }

    public function isOnRoutingPath(int $departmentId): bool
    {
        if ($this->relationLoaded('routeSteps')) {
            return $this->routeSteps->contains('department_id', $departmentId);
        }

        return $this->routeSteps()->where('department_id', $departmentId)->exists();
    }

    public function syncRouteSteps(array $departmentIds): void
    {
        $this->routeSteps()->delete();

        $order = 1;
        foreach (array_values(array_unique(array_map('intval', $departmentIds))) as $departmentId) {
            if ($departmentId <= 0) {
                continue;
            }

            $this->routeSteps()->create([
                'department_id' => $departmentId,
                'step_order' => $order++,
            ]);
        }
    }

    /**
     * The ordered chain of departments for this document. `route_steps` is the
     * single source of truth — RoutingRule only seeds defaults at creation time
     * (see DocumentWebController) and as a one-off backfill for legacy documents.
     */
    public function getRoutingChain()
    {
        $steps = $this->relationLoaded('routeSteps')
            ? $this->routeSteps
            : $this->routeSteps()->with('department')->get();

        return $steps->map(fn ($step) => $step->department)->filter()->values();
    }

    // Backward-compatible helper. Primary generation lives in QrCodeService.
    public static function generateTrackingNumber()
    {
        return 'SPD-'.date('Ymd').'-'.strtoupper(uniqid());
    }

    /**
     * @deprecated Routing-era positional advancement. Documents are advanced
     * manually through DocumentStatus stages; nothing calls this anymore.
     */
    public function getNextDepartment(): ?Department
    {
        if (! $this->current_department_id) {
            return null;
        }

        $steps = $this->relationLoaded('routeSteps')
            ? $this->routeSteps
            : $this->routeSteps()->orderBy('step_order')->get();

        $ids = $steps->sortBy('step_order')->pluck('department_id')->values();
        $index = $ids->search($this->current_department_id);

        if ($index !== false && isset($ids[$index + 1])) {
            return Department::find($ids[$index + 1]);
        }

        return null;
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
