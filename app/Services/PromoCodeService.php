<?php

namespace App\Services;

use App\Models\License;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Codes promotionnels :
 * - « discount » : réduction appliquée par Stripe (coupon + code promotionnel Stripe) ;
 * - « free_access » : licence offerte pour N jours, sans paiement ni carte bancaire.
 */
class PromoCodeService
{
    public function __construct(private StripeGateway $stripe, private Entitlements $entitlements) {}

    /** Crée le code ; pour une réduction, crée d'abord le coupon et le code dans Stripe. */
    public function create(array $data, User $author): PromoCode
    {
        $data['code'] = PromoCode::normalize($data['code']);
        if ($data['type'] === 'discount') {
            if (! $this->stripe->configured()) {
                throw ValidationException::withMessages(['type' => 'Configurez Stripe (Administration › Intégrations) avant de créer un code de réduction.']);
            }
            $coupon = array_filter([
                'name' => mb_substr($data['label'], 0, 40),
                'percent_off' => $data['percent_off'] ?? null,
                'amount_off' => isset($data['amount_off']) ? (int) round($data['amount_off'] * 100) : null,
                'currency' => isset($data['amount_off']) ? strtolower($data['currency'] ?? 'EUR') : null,
                'duration' => $data['duration'],
                'duration_in_months' => $data['duration'] === 'repeating' ? $data['duration_months'] : null,
                'metadata' => ['source' => 'jackcie-admin'],
            ], fn ($v) => $v !== null);
            $products = SubscriptionPlan::whereIn('id', $data['plan_ids'] ?? [])->whereNotNull('stripe_product_id')->pluck('stripe_product_id')->all();
            if ($products) {
                $coupon['applies_to'] = ['products' => $products];
            }
            $data['stripe_coupon_id'] = $this->stripe->createCoupon($coupon);
            $data['stripe_promotion_code_id'] = $this->stripe->createPromotionCode($data['stripe_coupon_id'], $data['code'], array_filter([
                'max_redemptions' => $data['max_redemptions'] ?? null,
                'expires_at' => isset($data['expires_at']) ? strtotime((string) $data['expires_at']) : null,
                'restrictions' => ! empty($data['first_subscription_only']) ? ['first_time_transaction' => true] : null,
            ], fn ($v) => $v !== null));
        }
        $promo = PromoCode::create($data + ['created_by' => $author->id]);
        Audit::log('admin.promo_code_created', $promo, ['code' => $promo->code, 'type' => $promo->type]);

        return $promo;
    }

    public function setActive(PromoCode $promo, bool $active): void
    {
        if ($promo->stripe_promotion_code_id) {
            $this->stripe->setPromotionCodeActive($promo->stripe_promotion_code_id, $active);
        }
        $promo->update(['is_active' => $active]);
        Audit::log($active ? 'admin.promo_code_enabled' : 'admin.promo_code_disabled', $promo, ['code' => $promo->code]);
    }

    /**
     * Modifie le libellé, la période de validité, le nombre maximal d'utilisations et la restriction
     * « nouveaux clients ». Stripe ne permettant pas de modifier ces réglages sur un code existant,
     * un code de réduction est remplacé chez Stripe par un nouveau code identique (même texte, même
     * coupon) portant les nouveaux réglages ; les utilisations déjà faites sont déduites de la limite.
     */
    public function updateSettings(PromoCode $promo, array $data): void
    {
        $promo->fill($data);
        $stripeFields = ['expires_at', 'max_redemptions', 'first_subscription_only'];
        if ($promo->stripe_promotion_code_id && $promo->isDirty($stripeFields)) {
            $old = $promo->stripe_promotion_code_id;
            $remaining = $promo->max_redemptions !== null ? $promo->max_redemptions - $promo->redemptions_count : null;
            $usable = $promo->is_active && ($remaining === null || $remaining > 0) && (! $promo->expires_at || $promo->expires_at->isFuture());
            $this->stripe->setPromotionCodeActive($old, false);
            if ($usable) {
                try {
                    $promo->stripe_promotion_code_id = $this->stripe->createPromotionCode($promo->stripe_coupon_id, $promo->code, array_filter([
                        'max_redemptions' => $remaining,
                        'expires_at' => $promo->expires_at?->endOfDay()->timestamp,
                        'restrictions' => $promo->first_subscription_only ? ['first_time_transaction' => true] : null,
                    ], fn ($v) => $v !== null));
                } catch (\Throwable $e) {
                    $this->stripe->setPromotionCodeActive($old, true);
                    throw $e;
                }
            }
        }
        $changes = array_keys($promo->getDirty());
        $promo->save();
        Audit::log('admin.promo_code_updated', $promo, ['code' => $promo->code, 'fields' => $changes]);
    }

    public function find(string $code): ?PromoCode
    {
        $code = PromoCode::normalize($code);

        return $code === '' ? null : PromoCode::where('code', $code)->first();
    }

    /** Active un code d'accès gratuit pour l'organisation. */
    public function redeemFreeAccess(string $code, Organization $org, User $user): License
    {
        return DB::transaction(function () use ($code, $org, $user) {
            $promo = $this->find($code);
            $promo = $promo ? PromoCode::lockForUpdate()->find($promo->id) : null;
            if (! $promo) {
                $this->fail('Code inconnu. Vérifiez la saisie.');
            }
            if ($promo->type === 'discount') {
                $this->fail('Ce code donne une réduction : saisissez-le au moment de souscrire une offre ci-dessous.');
            }
            if ($reason = $promo->unavailableReason()) {
                $this->fail($reason);
            }
            if ($promo->redemptions()->where('organization_id', $org->id)->exists()) {
                $this->fail('Ce code a déjà été utilisé pour cet espace.');
            }
            if (app(BillingService::class)->activeSubscription($org)) {
                $this->fail('Un abonnement payant est en cours sur cet espace : le code ne peut pas s\'y ajouter.');
            }
            $plan = $promo->plan;
            if (! $plan) {
                $this->fail('Ce code n\'est rattaché à aucune offre. Contactez le support.');
            }
            if ($plan->audience === 'stable' && $org->isPersonal()) {
                $this->fail('Ce code est réservé aux espaces écurie.');
            }
            if ($promo->first_subscription_only && License::where('organization_id', $org->id)->whereIn('source', ['stripe', 'code'])->exists()) {
                $this->fail('Ce code est réservé aux nouveaux clients.');
            }

            $license = License::create([
                'organization_id' => $org->id, 'subscription_plan_id' => $plan->id, 'source' => 'code', 'status' => 'active',
                'starts_at' => now(), 'ends_at' => now()->addDays($promo->free_days), 'promo_code_id' => $promo->id,
                'notes' => 'Code '.$promo->code.' — '.$promo->label,
            ]);
            PromoCodeRedemption::create(['promo_code_id' => $promo->id, 'organization_id' => $org->id, 'user_id' => $user->id, 'license_id' => $license->id]);
            $promo->increment('redemptions_count');
            $this->entitlements->flush($org);
            Audit::log('billing.promo_code_redeemed', $license, ['code' => $promo->code, 'days' => $promo->free_days], $org->id);

            return $license;
        });
    }

    /** Vérifie un code de réduction avant de l'appliquer à une session Stripe Checkout. */
    public function discountFor(string $code, Organization $org, SubscriptionPlan $plan): PromoCode
    {
        $promo = $this->find($code);
        if (! $promo) {
            $this->fail('Code inconnu. Vérifiez la saisie.', 'promo_code');
        }
        if ($promo->type !== 'discount' || ! $promo->stripe_promotion_code_id) {
            $this->fail('Ce code offre un accès gratuit : utilisez « J\'ai un code » en haut de la page.', 'promo_code');
        }
        if ($reason = $promo->unavailableReason()) {
            $this->fail($reason, 'promo_code');
        }
        if (! $promo->appliesToPlan($plan->id)) {
            $this->fail('Ce code ne s\'applique pas à l\'offre '.$plan->name.'.', 'promo_code');
        }
        if ($promo->redemptions()->where('organization_id', $org->id)->exists()) {
            $this->fail('Ce code a déjà été utilisé pour cet espace.', 'promo_code');
        }

        return $promo;
    }

    /** Enregistre l'utilisation d'un code de réduction à la confirmation du paiement (webhook). */
    public function recordCheckoutRedemption(int $promoId, Organization $org, ?string $sessionId, ?int $userId): void
    {
        $promo = PromoCode::find($promoId);
        if (! $promo || ($sessionId && PromoCodeRedemption::where('stripe_checkout_session_id', $sessionId)->exists())
            || $promo->redemptions()->where('organization_id', $org->id)->exists()) {
            return;
        }
        PromoCodeRedemption::create(['promo_code_id' => $promo->id, 'organization_id' => $org->id, 'user_id' => $userId, 'stripe_checkout_session_id' => $sessionId]);
        $promo->increment('redemptions_count');
        Audit::log('billing.promo_code_redeemed', $promo, ['code' => $promo->code], $org->id);
    }

    private function fail(string $message, string $field = 'code'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
