<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $guarded = ['id', 'redemptions_count'];

    protected $casts = [
        'is_active' => 'boolean', 'first_subscription_only' => 'boolean',
        'starts_at' => 'datetime', 'expires_at' => 'datetime', 'plan_ids' => 'array',
        'percent_off' => 'decimal:2', 'amount_off' => 'decimal:2',
    ];

    public const TYPES = ['discount' => 'Réduction sur l\'abonnement', 'free_access' => 'Accès gratuit pour une durée'];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function redemptions()
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code));
    }

    /** Raison pour laquelle le code est inutilisable, ou null s'il est valable. */
    public function unavailableReason(): ?string
    {
        return match (true) {
            ! $this->is_active => 'Ce code n\'est plus actif.',
            $this->starts_at && $this->starts_at->isFuture() => 'Ce code n\'est pas encore valable.',
            $this->expires_at && $this->expires_at->copy()->endOfDay()->isPast() => 'Ce code a expiré.',
            $this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions => 'Ce code a atteint son nombre maximal d\'utilisations.',
            default => null,
        };
    }

    public function appliesToPlan(int $planId): bool
    {
        return empty($this->plan_ids) || in_array($planId, array_map('intval', $this->plan_ids), true);
    }

    /** Description lisible pour l'utilisateur. */
    public function summary(): string
    {
        if ($this->type === 'free_access') {
            return ($this->plan?->name ?? 'Offre').' offert pendant '.$this->free_days.' jours';
        }
        $value = $this->percent_off ? rtrim(rtrim((string) $this->percent_off, '0'), '.').' %' : number_format((float) $this->amount_off, 2, ',', ' ').' '.($this->currency === 'EUR' ? '€' : $this->currency);
        $when = match ($this->duration) {
            'once' => 'sur la première facture',
            'repeating' => 'pendant '.$this->duration_months.' mois',
            default => 'pendant toute la durée de l\'abonnement',
        };

        return '−'.$value.' '.$when;
    }
}
