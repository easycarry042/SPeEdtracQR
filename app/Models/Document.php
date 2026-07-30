<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
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
        'origin',
        'requesting_department_id',
        'department_id',
        'amount',
        'notify_citizen',
        'description',
        'purpose',
        'quantity',
        'needed_by',
        'status',
        'created_by',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'decided_by',
        'decided_at',
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
        'claimed_at',
        'claim_date',
        'released_by',
    ];

    /** Allowed values for the `blocked_by` hold tag. */
    public const BLOCKED_BY = ['citizen', 'internal', 'external'];

    /** Citizen-facing ticket (guest/staff created) — the original flow. */
    public const ORIGIN_EXTERNAL = 'external';

    /** Dept-to-dept request (supervisor created) travelling an endorsement chain. */
    public const ORIGIN_INTERNAL = 'internal';

    protected $casts = [
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'decided_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'sla_warning_notified_at' => 'datetime',
        'sla_breach_notified_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'notify_citizen' => 'boolean',
        'amount' => 'decimal:2',
        'quantity' => 'integer',
        'needed_by' => 'date',
        'hold_until' => 'date',
        'held_at' => 'datetime',
        'claimed_at' => 'datetime',
        // The promised collection day (set when advancing), not the moment of
        // collection — that is `claimed_at`.
        'claim_date' => 'date',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Physical custody trail, newest first. */
    public function custodyEvents(): HasMany
    {
        return $this->hasMany(DocumentCustodyEvent::class)->latest();
    }

    /** Who physically holds the paper folder right now (latest custody event). */
    public function currentCustody(): ?DocumentCustodyEvent
    {
        return $this->custodyEvents()->with('user:id,name')->first();
    }

    /** Whether this is an internal dept-to-dept request (vs a citizen ticket). */
    public function isInternal(): bool
    {
        return $this->origin === self::ORIGIN_INTERNAL;
    }

    /** Office that filed this internal request (null for external tickets). */
    public function requestingDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requesting_department_id');
    }

    /**
     * Department currently handling this ticket (from the request type). Its
     * Supervisor assigns the responsible staff member.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Endorsement chain of an internal request, in hop order. */
    public function requestSteps(): HasMany
    {
        return $this->hasMany(RequestStep::class)->orderBy('step_order')->orderBy('id');
    }

    /** The hop the request is sitting at right now (null when no chain or all done). */
    public function currentRequestStep(): ?RequestStep
    {
        return $this->requestSteps()->where('status', RequestStep::STATUS_CURRENT)->first();
    }

    /**
     * Log a department onto the endorsement chain — used when an office scans
     * the folder into its custody. The chain is a growing record of who handled
     * the paper, not a route fixed at filing: it ends only when a department
     * marks the request done.
     *
     * The office that was holding it is closed as `forwarded` (it passed the
     * paper on) unless it had already been formally acted on.
     */
    public function appendEndorsementStep(int $departmentId, string $action = 'Received and handling'): RequestStep
    {
        $open = $this->requestSteps()
            ->whereIn('status', [RequestStep::STATUS_CURRENT, RequestStep::STATUS_PENDING])
            ->get();

        foreach ($open as $step) {
            $step->update([
                'status' => $step->status === RequestStep::STATUS_CURRENT
                    ? RequestStep::STATUS_FORWARDED
                    : RequestStep::STATUS_SKIPPED,
            ]);
        }

        $step = $this->requestSteps()->create([
            'step_order' => (int) $this->requestSteps()->max('step_order') + 1,
            'department_id' => $departmentId,
            'action' => $action,
            'status' => RequestStep::STATUS_CURRENT,
            'started_at' => now(),
        ]);

        $this->unsetRelation('requestSteps');

        return $step;
    }

    /**
     * A human, chain-aware headline for an internal request — "Awaiting
     * endorsement", "Under review", "Returned for revision", "Denied",
     * "Completed" — derived from the endorsement chain rather than the
     * citizen-oriented DocumentStatus enum. The office currently holding the
     * request is shown alongside (see {@see currentRequestStep()}).
     */
    public function internalStatusLabel(): string
    {
        return match ($this->statusEnum()) {
            DocumentStatus::Completed => 'Completed',
            DocumentStatus::Denied => 'Denied',
            DocumentStatus::Returned => 'Returned for revision',
            default => (function (): string {
                $step = $this->currentRequestStep();

                if (! $step instanceof RequestStep) {
                    return $this->statusEnum()->label();
                }

                // step_order 0 is the requesting department's own endorsement hop.
                return $step->step_order === 0 && (int) $step->department_id === (int) $this->requesting_department_id
                    ? 'Awaiting endorsement'
                    : 'Under review';
            })(),
        };
    }

    /**
     * Colour band for {@see internalStatusLabel()} — maps to a pill/dot tone.
     * One of: green | amber | red | returned.
     */
    public function internalStatusBand(): string
    {
        return match ($this->statusEnum()) {
            DocumentStatus::Completed => 'green',
            DocumentStatus::Denied => 'red',
            DocumentStatus::Returned => 'returned',
            default => 'amber',
        };
    }

    /**
     * Whether this user is the one who must act on the request's current hop:
     * a supervisor of the department the request is sitting at. Single source
     * of truth for RequestStepController's gate and the action panel in views.
     */
    public function canActOnCurrentStep(?User $user): bool
    {
        if (! $user || ! $this->isInternal()) {
            return false;
        }

        $step = $this->currentRequestStep();

        return $step instanceof RequestStep
            && $user->can('act on internal requests')
            && $user->department_id !== null
            && (int) $user->department_id === (int) $step->department_id;
    }

    /**
     * Whether the office holding the current hop has physically taken custody
     * of the folder since that hop began. This is the precondition for acting
     * on the endorsement chain: it binds every digital endorsement to a QR scan
     * (an audited manual override still counts). Custody recorded while the
     * request sat at an earlier office does not carry over to the new hop.
     */
    public function currentStepHasCustody(): bool
    {
        $step = $this->currentRequestStep();

        if (! $step instanceof RequestStep || $step->started_at === null) {
            return false;
        }

        return $this->custodyEvents()
            ->where('created_at', '>=', $step->started_at)
            ->whereHas('user', fn ($query) => $query->where('department_id', $step->department_id))
            ->exists();
    }

    /** Staff member responsible for advancing this document through its stages. */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Whoever denied or otherwise closed the request. */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Name + role of the person who closed this request, for the citizen page.
     *
     * Falls back to the activity log so requests denied before `decided_by`
     * existed still name their decider instead of showing nothing.
     *
     * @return array{name: string, role: string}|null
     */
    public function decisionActor(): ?array
    {
        if ($this->decidedBy) {
            return [
                'name' => $this->decidedBy->name,
                'role' => self::roleLabelFor($this->decidedBy),
            ];
        }

        $causer = $this->activities()
            ->whereNotNull('causer_id')
            ->where('description', 'like', 'Denied%')
            ->latest('id')
            ->first()?->causer;

        if (! $causer instanceof User) {
            return null;
        }

        return [
            'name' => $causer->name,
            'role' => self::roleLabelFor($causer),
        ];
    }

    /** Citizen-facing role wording — never the raw Spatie role name. */
    private static function roleLabelFor(User $user): string
    {
        return match (true) {
            $user->hasRole('super_admin') => 'Administrator',
            $user->hasRole('Supervisor') => 'Supervisor',
            $user->hasRole('department_admin') => 'Department Head',
            default => 'Staff',
        };
    }

    /** Admin who made the current assignment. */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Staff member / admin who placed the document on hold. */
    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class)->orderBy('sort_order');
    }

    /** Supporting-requirement checklist for this request (Cedula, clearances, …). */
    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class)->orderBy('id');
    }

    /** Resource reservation for a booking-kind request (null otherwise). */
    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /** Mandatory requirements a staffer has not yet verified — blocks completion. */
    public function unverifiedMandatoryRequirements(): Collection
    {
        return $this->requirements()->where('is_mandatory', true)->whereNull('verified_at')->get();
    }

    /** Top-level collaboration posts (replies are loaded via the `replies` relation). */
    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class)->whereNull('parent_id')->latest();
    }

    /**
     * Every message on the request, replies included.
     *
     * Read state and unread counts must use this, not `comments()`: a citizen's
     * answer under a staff message is a reply, so counting only top-level posts
     * would leave it permanently unread and its badge permanently lit.
     */
    public function allComments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
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
    public static function generateTrackingNumber(): string
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

    /**
     * Only the assigned staff member (holding `advance documents`) or an admin
     * (`manage system` / `assign documents`) may move this document's status
     * stage. Single source of truth for DocumentStatusController's server-side
     * gate and every place that renders the advance/revert/hold controls, so
     * the UI never offers an action the backend will then reject.
     */
    public function canBeAdvancedBy(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->can('manage system') || $user->can('assign documents')) {
            return true;
        }

        return $user->can('advance documents')
            && $this->assigned_to !== null
            && (int) $this->assigned_to === (int) $user->id;
    }
}
