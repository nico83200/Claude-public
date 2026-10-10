<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\SubscriptionPlan;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripeGateway;
use App\Support\Perm;
use Illuminate\Http\Request;
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
        ]);
    }

    public function checkout(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        abort_unless($this->stripe->configured() && FeatureFlag::enabled('billing'), 503, 'Le paiement en ligne n\'est pas disponible.');
        $data = $request->validate(['plan_id' => ['required', 'integer'], 'interval' => ['required', Rule::in(['month', 'year'])], 'accept' => ['accepted']],
            ['accept.accepted' => 'Veuillez accepter les conditions de l\'abonnement.']);
        $plan = SubscriptionPlan::purchasable()->findOrFail($data['plan_id']);

        try {
            return redirect()->away($this->billing->checkout($org, $request->user(), $plan, $data['interval']));
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Le service de paiement est momentanément indisponible. Réessayez plus tard.');
        }
    }

    /** Retour de Stripe Checkout : informatif uniquement, l'activation vient du webhook. */
    public function returned()
    {
        return redirect()->route('billing.index')->with('status', 'Merci ! Votre paiement est en cours de confirmation par Stripe. L\'abonnement sera activé dès réception de la confirmation (quelques secondes en général).');
    }

    public function portal(Request $request)
    {
        $org = $this->currentOrganization();
        $this->authorizeOrg($org, Perm::BILLING_MANAGE);
        abort_unless($org->stripeCustomer && $this->stripe->configured(), 404);

        try {
            return redirect()->away($this->stripe->createPortalSession($org->stripeCustomer->stripe_customer_id, route('billing.index')));
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
