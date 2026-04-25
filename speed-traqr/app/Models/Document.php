<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Document extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'tracking_number', 'document_type', 'citizen_name', 'status',
        'current_department_id', 'created_by', 'remarks', 'completed_at'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tracking_number', 'status', 'current_department_id'])
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

    // Generate unique tracking number
    public static function generateTrackingNumber()
    {
        return 'SPD-' . date('Ymd') . '-' . strtoupper(uniqid());
    }

    // Get next department based on routing rule
    public function getNextDepartment()
    {
        $currentStep = RoutingRule::where('document_type', $this->document_type)
            ->where('from_department_id', $this->current_department_id)
            ->first();

        if ($currentStep) {
            return Department::find($currentStep->to_department_id);
        }
        return null;
    }

    // Check if document is overdue
    public function isOverdue()
    {
        if (!$this->current_department_id || $this->status === 'completed') {
            return false;
        }

        $lastScan = $this->scans()->where('action', 'in')
            ->where('department_id', $this->current_department_id)
            ->latest('scanned_at')
            ->first();

        if (!$lastScan) return false;

        $hoursStayed = now()->diffInHours($lastScan->scanned_at);
        $sla = $this->currentDepartment->sla_hours;

        return $hoursStayed > $sla;
    }
}