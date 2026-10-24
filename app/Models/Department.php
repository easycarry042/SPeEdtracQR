<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A municipal office (Mayor's Office, Budget Office, BAC, …). Drives routing
 * of internal dept-to-dept requests; external citizen tickets never touch it.
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /** The turnaround an office gets when no one has set one. */
    public const DEFAULT_INTERNAL_STEP_HOURS = 72;

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'internal_step_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'internal_step_hours' => 'integer',
    ];

    /** Hours this office has to act on an internal request that reached it. */
    public function internalStepHours(): int
    {
        return $this->internal_step_hours ?: self::DEFAULT_INTERNAL_STEP_HOURS;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** Citizen request types this office handles. */
    public function requestTypes()
    {
        return $this->hasMany(RequestType::class);
    }

    /** Internal requests originating from this office. */
    public function requestedDocuments()
    {
        return $this->hasMany(Document::class, 'requesting_department_id');
    }

    protected function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
