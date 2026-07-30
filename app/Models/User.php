<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\StaffBadge;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'department_id', 'signature_path'])]
#[Hidden(['password', 'remember_token', 'identity_code'])]
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
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * This user's staff-badge code, minted on first use.
     *
     * Printed as a QR on their badge and scanned to re-confirm identity when
     * signing an endorsement hop, replacing the password prompt. It is a secret,
     * so it is hidden from serialisation and never shown in listings.
     */
    public function badgeCode(): string
    {
        if (! $this->identity_code) {
            $this->forceFill(['identity_code' => Str::random(32)])->save();
        }

        return $this->identity_code;
    }

    /** The exact payload the badge QR encodes (see App\Support\StaffBadge). */
    public function badgePayload(): string
    {
        return StaffBadge::payload($this->badgeCode());
    }

    /** Issue a new badge code, invalidating any previously printed badge. */
    public function regenerateBadgeCode(): string
    {
        $this->forceFill(['identity_code' => Str::random(32)])->save();

        return $this->identity_code;
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
