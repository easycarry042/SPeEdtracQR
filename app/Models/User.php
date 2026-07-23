<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'department_id', 'signature_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    // SoftDeletes powers "archive": archived users are excluded from auth and queries until restored.
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    /**
     * The municipal office this user belongs to (null for org-wide accounts).
     */
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
     * Documents this user is responsible for advancing through its status stages.
     */
    public function assignedDocuments()
    {
        return $this->hasMany(Document::class, 'assigned_to');
    }

    /**
     * Manual highlight posts authored by this user for their staff profile feed.
     */
    public function highlights()
    {
        return $this->hasMany(StaffHighlight::class)->latest();
    }
}
