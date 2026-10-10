<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'suspended_at', 'suspension_reason'];

    protected $casts = ['settings' => 'array', 'suspended_at' => 'datetime', 'is_demo' => 'boolean'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_members')->withPivot('role_id')->withTimestamps();
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    /** Rôles utilisables : rôles système + rôles personnalisés de l'espace. */
    public function availableRoles()
    {
        return Role::whereNull('organization_id')->orWhere('organization_id', $this->id)->orderBy('is_system', 'desc')->orderBy('name')->get();
    }

    public function horses()
    {
        return $this->hasMany(Horse::class);
    }

    public function assignments()
    {
        return $this->hasMany(HorseOrganizationAssignment::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function stripeCustomer()
    {
        return $this->hasOne(StripeCustomer::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentRecord::class);
    }

    public function professionals()
    {
        return $this->hasMany(Professional::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === 'personal';
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function typeLabel(): string
    {
        return $this->isPersonal() ? 'Espace personnel' : 'Écurie';
    }
}
