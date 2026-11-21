<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'is_active',
        'google_id',
        'facebook_id',
        'password_set',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'password_set'      => 'boolean',
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Check if user is a SuperAdmin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value);
    }

    /**
     * Check if user is Platform Support Staff.
     */
    public function isPlatformSupport(): bool
    {
        return $this->hasRole(\App\Enums\RoleEnum::PLATFORM_SUPPORT->value);
    }

    /**
     * Check if user is the Business Owner / Merchant Admin.
     */
    public function isBusinessAdmin(): bool
    {
        return $this->hasRole(\App\Enums\RoleEnum::ADMIN->value);
    }

    /**
     * Check if user is a Store Manager.
     */
    public function isWorkspaceManager(): bool
    {
        return $this->hasRole(\App\Enums\RoleEnum::WORKSPACE_MANAGER->value);
    }

    /**
     * Check if user is a Chat / Support Agent.
     */
    public function isWorkspaceAgent(): bool
    {
        return $this->hasRole(\App\Enums\RoleEnum::WORKSPACE_AGENT->value);
    }

    /**
     * Check if user is an employee / staff member of a workspace.
     */
    public function isWorkspaceStaff(): bool
    {
        return $this->isWorkspaceManager() || $this->isWorkspaceAgent();
    }

    /**
     * Get the formatted avatar URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        if (str_starts_with($this->avatar, 'uploads/') || str_starts_with($this->avatar, 'upload/')) {
            return asset($this->avatar);
        }

        if (file_exists(public_path('storage/' . $this->avatar))) {
            return asset('storage/' . $this->avatar);
        }

        return asset($this->avatar);
    }
}
