<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentScan extends Model
{
    protected $fillable = [
        'document_id',
        'scanned_by',
        'department_id',
        'action',
        'scanned_at',
        'location_ip',
        'remarks',
        'attachment_path',
        'offline_uuid',
        'synced',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'synced' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
