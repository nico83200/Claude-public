<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\SubscriptionPlan;
use App\Services\Audit;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripeGateway;
use App\Services\PromoCodeService;
use App\Services\Upsell;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingController extends Controller
{
    public function __construct(private BillingService $billing, private StripeGateway $stripe) {}

    public function index(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        $this->refreshFromStripe($org, $request->boolean('maj'));
        // Arrivée depuis une proposition d'offre : comptée (statistiques de l'administration), sans données personnelles.
        if (array_key_exists((string) $request->query('fonction'), Upsell::PITCHES)) {
            Audit::log('upsell.clicked', null, ['key' => $request->query('fonction')], $org->id);
        }

        return view('billing.index', [
            'org' => $org,
            'ent' => $this->entitlements()->for($org),
            'subscription' => $this->billing->activeSubscription($org)?->load('plan'),
            'plans' => SubscriptionPlan::purchasable()->whereIn('audience', $org->isPersonal() ? ['individual', 'any'] : ['stable', 'any', 'individual'])->orderBy('sort_order')->get(),
            'payments' => $org->payments()->latest()->limit(24)->get(),
            'usage' => [
                'horses' => $org->horses()->whereNull('archived_at')->count(),
                'members' => $org->members()->count(),
                'storage_mb' => round($this->entitlements()->storageUsedBytes($org) / 1048576, 1),
            ],
            'stripeReady' => $this->stripe->configured() && FeatureFlag::enabled('billing'),
            'cancelled' => $request->boolean('annule'),
            // Arrivée depuis une proposition d'offre supérieure : fonction ou limite à mettre en avant.
            'focus' => array_key_exists((string) $request->query('fonction'), Upsell::PITCHES) ? (string) $request->query('fonction') : null,
            'trialAvailable' => ! License::where('organization_id', $org->id)->where('source', 'stripe')->exists(),
        ]);
    }

    public function checkout(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        abort_unless($this->stripe->configured() && FeatureFlag::enabled('billing'), 503, 'Le paiement en ligne n\'est pas disponible.');
        $data = $request->validate(['plan_id' => ['required', 'integer'], 'interval' => ['required', Rule::in(['month', 'year'])], 'accept' => ['accepted'], 'promo_code' => ['nullable', 'string', 'max:40']],
            ['accept.accepted' => 'Veuillez accepter les conditions de l\'abonnement.']);
        $plan = SubscriptionPlan::purchasable()->findOrFail($data['plan_id']);
        $promo = filled($data['promo_code'] ?? null) ? app(PromoCodeService::class)->discountFor($data['promo_code'], $org, $plan) : null;

        try {
            return redirect()->away($this->billing->checkout($org, $request->user(), $plan, $data['interval'], $promo));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Le service de paiement est momentanément indisponible. Réessayez plus tard.');
        }
    }

    /** Active un code d'accès gratuit (licence offerte pour une durée, sans paiement). */
    public function redeem(Request $request, PromoCodeService $promos)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $license = $promos->redeemFreeAccess($data['code'], $org, $request->user());

        return back()->with('success', 'Code activé : '.$license->plan->name.' offert jusqu\'au '.$license->ends_at->format('d/m/Y').'.');
    }

    /**
     * Retour de Stripe Checkout : la session est relue chez Stripe pour activer sans attendre le webhook.
     * L'identifiant passé dans l'URL ne suffit pas : seule la réponse de l'API Stripe fait foi.
     */
    public function returned(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        $sessionId = (string) $request->query('session_id');
        if (str_starts_with($sessionId, 'cs_') && $this->stripe->configured()) {
            try {
                if ($sub = $this->billing->confirmCheckout($org, $sessionId)) {
                    return redirect()->route('billing.index')->with('success', 'Merci ! Votre abonnement '.$sub->plan->name.' est actif'
                        .($sub->stripe_status === 'trialing' && $sub->trial_ends_at ? ' (essai gratuit jusqu\'au '.$sub->trial_ends_at->format('d/m/Y').').' : '.'));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('billing.index')->with('status', 'Merci ! Votre paiement est en cours de confirmation par Stripe. L\'abonnement sera activé dès réception de la confirmation (quelques secondes en général).');
    }

    public function portal(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        abort_unless($org->stripeCustomer && $this->stripe->configured(), 404);

        try {
            return redirect()->away($this->stripe->createPortalSession($org->stripeCustomer->stripe_customer_id, route('billing.index', ['maj' => 1])));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Le portail de facturation est momentanément indisponible.');
        }
    }

    public function swap(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        $data = $request->validate(['plan_id' => ['required', 'integer'], 'interval' => ['required', Rule::in(['month', 'year'])]]);
        $plan = SubscriptionPlan::purchasable()->findOrFail($data['plan_id']);
        $this->guard(fn () => $this->billing->swap($org, $plan, $data['interval']));

        return back()->with('success', 'Changement d\'offre demandé. Il sera effectif dès confirmation par Stripe (prorata appliqué).');
    }

    public function cancel(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        $this->guard(fn () => $this->billing->cancel($org));

        return back()->with('success', 'Résiliation programmée à la fin de la période en cours. Vos données restent accessibles en lecture et exportables ensuite.');
    }

    public function resume(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        $this->guard(fn () => $this->billing->resume($org));

        return back()->with('success', 'Reprise de l\'abonnement demandée.');
    }

    /**
     * Rattrapage si un webhook manque : l'état est relu chez Stripe à l'ouverture de la page
     * (au plus toutes les 5 minutes, ou immédiatement au retour du portail de facturation).
     */
    private function refreshFromStripe($org, bool $force): void
    {
        if (! $this->stripe->configured() || ! $this->billing->activeSubscription($org)) {
            return;
        }
        if (! $force && ! Cache::add('billing-refresh:'.$org->id, 1, now()->addMinutes(5))) {
            return;
        }
        try {
            $this->billing->refreshOpenSubscriptions($org);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            abort(back()->with('error', 'Le service de paiement est momentanément indisponible.'));
        }
    }
}
