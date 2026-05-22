<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutingRule extends Model
{
    protected $fillable = [
        'document_type',
        'from_department_id',
        'to_department_id',
        'step_order',
    ];

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }
}
