<?php

namespace App\Services\Assistant;

use App\Models\ApplicationSetting;
use App\Models\AssistantCreditPurchase;
use App\Models\AssistantRequest;
use App\Models\FeatureFlag;
use App\Models\Organization;
use App\Models\StripeCustomer;
use App\Models\User;
use App\Services\Audit;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripeGateway;
use App\Services\Entitlements;
use App\Support\Perm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crédits IA achetés en plus du quota mensuel de l'offre (1 crédit = 1 consigne ou 1 lecture IA).
 * Le solde n'augmente qu'après un paiement confirmé par Stripe (webhook signé ou session relue chez Stripe) ;
 * il est consommé seulement une fois le quota mensuel épuisé, et rendu si la demande échoue.
 */
class AssistantCredits
{
    public const PACKS_KEY = 'assistant_credit_packs';

    public const ENABLED_KEY = 'assistant_credits_enabled';

    /** Packs proposés tant que le super-administrateur ne les a pas modifiés (Administration › Assistant IA). */
    public const DEFAULT_PACKS = [
        ['credits' => 50, 'price_cents' => 490],
        ['credits' => 150, 'price_cents' => 1190],
        ['credits' => 400, 'price_cents' => 2490],
    ];

    public function __construct(private StripeGateway $stripe, private Entitlements $entitlements) {}

    public static function enabled(): bool
    {
        return (bool) (ApplicationSetting::get(self::ENABLED_KEY) ?? true);
    }

    /** @return array<int, array{credits: int, price_cents: int}> */
    public static function packs(): array
    {
        $packs = collect((array) (ApplicationSetting::get(self::PACKS_KEY) ?? self::DEFAULT_PACKS))
            ->map(fn ($p) => ['credits' => (int) ($p['credits'] ?? 0), 'price_cents' => (int) ($p['price_cents'] ?? 0)])
            ->filter(fn ($p) => $p['credits'] > 0 && $p['price_cents'] >= 50)
            ->sortBy('credits')->values()->all();

        return $packs;
    }

    /** Achat possible sur cette instance (paiement en ligne actif et au moins un pack). */
    public function purchasable(): bool
    {
        return self::enabled() && $this->stripe->configured() && FeatureFlag::enabled('billing') && self::packs() !== [];
    }

    /** L'utilisateur peut-il acheter pour cet espace ? */
    public function canBuy(Organization $org, ?User $user): bool
    {
        return $this->purchasable() && (bool) $user?->canInOrg($org, Perm::BILLING_MANAGE);
    }

    public static function price(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }

    // ------------------------------------------------------------------ Consommation

    /**
     * À la création d'une demande IA : au-delà du quota mensuel, un crédit acheté est utilisé.
     * Une demande en erreur ne consomme rien.
     */
    public function charge(AssistantRequest $request): void
    {
        if ($request->status === 'error' || $request->paid_with_credit || ! $request->organization_id) {
            return;
        }
        $org = Organization::find($request->organization_id);
        $limit = $org ? $this->entitlements->limit($org, 'assistant_monthly') : null;
        if ($limit === null) {
            return;
        }
        $usedBefore = AssistantRequest::where('organization_id', $org->id)->where('id', '!=', $request->id)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'error'))
            ->where('created_at', '>=', now()->startOfMonth())->count();
        if ($usedBefore < $limit) {
            return;
        }
        if (Organization::whereKey($org->id)->where('assistant_credits', '>', 0)->decrement('assistant_credits')) {
            $request->forceFill(['paid_with_credit' => true])->saveQuietly();
        }
    }

    /** Demande finalement en erreur : le crédit est rendu. */
    public function refund(AssistantRequest $request): void
    {
        if ($request->paid_with_credit && $request->status === 'error') {
            Organization::whereKey($request->organization_id)->increment('assistant_credits');
            $request->forceFill(['paid_with_credit' => false])->saveQuietly();
        }
    }

    // ------------------------------------------------------------------ Achat

    /** Ouvre une page de paiement Stripe pour un pack ; renvoie son adresse. */
    public function checkout(Organization $org, User $user, int $pack): string
    {
        $packs = self::packs();
        if (! $this->canBuy($org, $user) || ! isset($packs[$pack])) {
            throw ValidationException::withMessages(['pack' => 'Ce pack de crédits n\'est pas disponible.']);
        }
        ['credits' => $credits, 'price_cents' => $cents] = $packs[$pack];
        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            'customer' => app(BillingService::class)->customerFor($org, $user),
            'client_reference_id' => (string) $org->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $cents,
                    'product_data' => ['name' => $credits.' crédits IA Jack&Cie', 'description' => 'Consignes de l\'assistant et lectures IA en plus du quota mensuel de votre offre. Sans date d\'expiration.'],
                ],
            ]],
            'metadata' => ['kind' => 'assistant_credits', 'organization_id' => (string) $org->id, 'user_id' => (string) $user->id, 'credits' => (string) $credits],
            'payment_intent_data' => ['metadata' => ['kind' => 'assistant_credits', 'organization_id' => (string) $org->id]],
            'invoice_creation' => ['enabled' => true],
            'locale' => 'fr',
            'success_url' => route('billing.credits.return').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.index').'?annule=1#credits-ia',
        ]);
        AssistantCreditPurchase::create([
            'organization_id' => $org->id, 'user_id' => $user->id, 'credits' => $credits, 'amount_cents' => $cents,
            'currency' => 'eur', 'stripe_session_id' => $session['id'], 'status' => 'pending',
        ]);
        Audit::log('assistant_credits.checkout_started', $org, ['credits' => $credits, 'amount_cents' => $cents], $org->id);

        return $session['url'];
    }

    /**
     * Session Checkout payée (webhook signé ou session relue chez Stripe) : crédits ajoutés une seule fois.
     * Les quantités viennent de l'achat enregistré à l'ouverture du paiement, jamais du navigateur.
     */
    public function fulfil(array $session): ?AssistantCreditPurchase
    {
        if (($session['mode'] ?? null) !== 'payment' || ($session['metadata']['kind'] ?? null) !== 'assistant_credits' || ($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        return DB::transaction(function () use ($session) {
            $purchase = AssistantCreditPurchase::lockForUpdate()->firstWhere('stripe_session_id', $session['id'] ?? '');
            if (! $purchase || (int) ($session['client_reference_id'] ?? 0) !== $purchase->organization_id || (int) ($session['amount_total'] ?? -1) !== $purchase->amount_cents) {
                return null;
            }
            if ($purchase->status === 'paid') {
                return $purchase;
            }
            $purchase->forceFill([
                'status' => 'paid', 'paid_at' => now(),
                'stripe_payment_intent_id' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : ($session['payment_intent']['id'] ?? null),
            ])->save();
            Organization::whereKey($purchase->organization_id)->increment('assistant_credits', $purchase->credits);
            if (! empty($session['customer'])) {
                StripeCustomer::firstOrCreate(['organization_id' => $purchase->organization_id], ['stripe_customer_id' => $session['customer']]);
            }
            Audit::log('assistant_credits.purchased', null, ['credits' => $purchase->credits, 'amount_cents' => $purchase->amount_cents], $purchase->organization_id);

            return $purchase;
        });
    }

    /** Retour de Stripe : la session est relue chez Stripe (seule source qui fait foi). */
    public function confirm(Organization $org, string $sessionId): ?AssistantCreditPurchase
    {
        $session = $this->stripe->retrieveCheckoutSession($sessionId);
        if ((int) ($session['client_reference_id'] ?? 0) !== $org->id) {
            return null;
        }

        return $this->fulfil($session);
    }
}
