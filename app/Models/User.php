<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;  

#[Fillable(['name', 'email', 'password', 'department_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;  

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Documents that this user created
     */
    public function createdDocuments()
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    /**
     * Scans performed by this user
     */
    public function scans()
    {
        return $this->hasMany(DocumentScan::class, 'scanned_by');
    }
}