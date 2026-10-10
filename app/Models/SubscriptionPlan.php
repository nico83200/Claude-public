<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_default_free' => 'boolean',
        'archived_at' => 'datetime',
    ];

    /** Fonctionnalités activables par offre. */
    public const FEATURES = [
        'sharing' => 'Partage et demi-pensions',
        'stable' => 'Gestion d\'écurie (membres, rôles)',
        'offline' => 'Mode hors ligne',
        'horse_search' => 'Recherche d\'identité sur Internet',
        'budget' => 'Suivi des dépenses',
        'exports' => 'Exports PDF / CSV',
        'documents' => 'Stockage de documents',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function priceChanges()
    {
        return $this->hasMany(PlanPriceChange::class);
    }

    public function scopePurchasable($q)
    {
        return $q->where('is_active', true)->where('is_public', true)->whereNull('archived_at')->where('is_default_free', false);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function stripePriceFor(string $interval): ?string
    {
        return $interval === 'year' ? $this->stripe_price_yearly_id : $this->stripe_price_monthly_id;
    }

    public function isInUse(): bool
    {
        return $this->subscriptions()->exists() || $this->licenses()->exists();
    }
}
