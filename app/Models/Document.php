<?php

namespace App\Models;

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
        'description',
        'purpose',
        'status',
        'current_department_id',
        'created_by',
        'remarks',
        'qr_code_path',
        'attachment_path',
        'received_at',
        'completed_at',
        'sla_warning_notified_at',
        'sla_breach_notified_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'sla_warning_notified_at' => 'datetime',
        'sla_breach_notified_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tracking_number', 'status', 'current_department_id',
                'citizen_name', 'citizen_contact', 'document_type', 'purpose', 'description', 'remarks',
            ])
            ->logOnlyDirty();
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

    public function routeSteps()
    {
        return $this->hasMany(DocumentRouteStep::class)->orderBy('step_order');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class)->orderBy('sort_order');
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

    // Check if document is overdue
    public function isOverdue()
    {
        if (! $this->current_department_id || $this->status === 'completed') {
            return false;
        }

        $lastScan = $this->scans()->where('action', 'in')
            ->where('department_id', $this->current_department_id)
            ->latest('scanned_at')
            ->first();

        if (! $lastScan) {
            return false;
        }

        // scanned_at is in the past; order operands so the diff is positive.
        $hoursStayed = $lastScan->scanned_at->diffInHours(now());
        $sla = $this->currentDepartment->sla_hours;

        return $hoursStayed > $sla;
    }
}
