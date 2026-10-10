<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'limit_overrides' => 'array',
        'feature_overrides' => 'array',
    ];

    public const STATUSES = [
        'pending' => 'En attente',
        'active' => 'Actif',
        'grace' => 'Période de grâce',
        'cancel_scheduled' => 'Résiliation programmée',
        'expired' => 'Expiré',
        'suspended' => 'Suspendu',
        'blocked' => 'Bloqué',
    ];

    /** États donnant accès aux fonctionnalités en écriture. */
    public const WRITABLE = ['active', 'grace', 'cancel_scheduled'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public const SOURCES = ['stripe' => 'Abonnement Stripe', 'manual' => 'Attribuée par l\'administration', 'trial' => 'Essai', 'free' => 'Offre gratuite', 'code' => 'Code d\'accès offert'];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
