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

    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
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
