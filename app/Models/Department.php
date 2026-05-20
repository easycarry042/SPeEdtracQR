<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'email',
        'sla_hours',
        'deleted_at',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'current_department_id');
    }
}