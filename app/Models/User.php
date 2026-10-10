<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    // is_super_admin et suspended_at ne sont jamais assignables en masse.
    protected $fillable = ['name', 'email', 'password', 'locale', 'current_organization_id', 'terms_accepted_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_members')->withPivot('role_id')->withTimestamps();
    }

    public function currentOrganization()
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function personalOrganization(): ?Organization
    {
        return Organization::where('owner_id', $this->id)->where('type', 'personal')->first();
    }

    public function accessGrants()
    {
        return $this->hasMany(HorseAccessGrant::class);
    }

    public function membershipIn(int|Organization $organization): ?OrganizationMember
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        return $this->memberships->firstWhere('organization_id', $id);
    }

    /** Permissions de l'utilisateur dans une organisation donnée (rôle). */
    public function orgPermissions(int|Organization $organization): array
    {
        $member = $this->membershipIn($organization);

        return $member ? $member->role->permissionKeys() : [];
    }

    public function canInOrg(int|Organization $organization, string $permission): bool
    {
        return in_array($permission, $this->orgPermissions($organization), true);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function displayName(): string
    {
        return $this->name;
    }
}
