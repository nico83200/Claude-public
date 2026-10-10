<?php

namespace App\Services\Billing;

use App\Models\License;
use App\Models\Organization;
use App\Models\PaymentRecord;
use App\Models\PlanPriceChange;
use App\Models\StripeCustomer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Relation de facturation Stripe et synchronisation vers les licences.
 * Seuls les événements Stripe vérifiés modifient l'état des abonnements :
 * la page de retour après paiement n'active jamais rien.
 */
class BillingService
{
    public function __construct(private StripeGateway $stripe, private Entitlements $entitlements) {}

    public function customerFor(Organization $org, User $user): string
    {
        if ($existing = $org->stripeCustomer) {
            return $existing->stripe_customer_id;
        }
        $id = $this->stripe->createCustomer($org->email ?: $user->email, $org->name, ['organization_id' => (string) $org->id]);
        StripeCustomer::create(['organization_id' => $org->id, 'stripe_customer_id' => $id, 'email' => $org->email ?: $user->email]);

        return $id;
    }

    public function activeSubscription(Organization $org): ?Subscription
    {
        return $org->subscriptions()->whereNotIn('stripe_status', ['canceled', 'incomplete_expired'])->latest('id')->first();
    }

    public function checkout(Organization $org, User $user, SubscriptionPlan $plan, string $interval): string
    {
        if (! $plan->is_active || $plan->archived_at || $plan->is_default_free) {
            throw ValidationException::withMessages(['plan' => 'Cette offre n\'est pas disponible à la souscription.']);
        }
        if ($plan->audience === 'stable' && $org->isPersonal()) {
            throw ValidationException::withMessages(['plan' => 'Cette offre est destinée aux écuries : créez d\'abord un espace écurie.']);
        }
        $price = $plan->stripePriceFor($interval);
        if (! $price) {
            throw ValidationException::withMessages(['plan' => 'Le tarif '.($interval === 'year' ? 'annuel' : 'mensuel').' de cette offre n\'est pas encore configuré.']);
        }
        if ($this->activeSubscription($org)) {
            throw ValidationException::withMessages(['plan' => 'Un abonnement est déjà en cours : utilisez « Changer d\'offre ».']);
        }

        $hadTrial = License::where('organization_id', $org->id)->where('source', 'stripe')->exists();
        $subscriptionData = ['metadata' => ['organization_id' => (string) $org->id, 'plan_id' => (string) $plan->id]];
        if ($plan->trial_days > 0 && ! $hadTrial) {
            $subscriptionData['trial_period_days'] = $plan->trial_days;
        }

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'subscription',
            'customer' => $this->customerFor($org, $user),
            'client_reference_id' => (string) $org->id,
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'subscription_data' => $subscriptionData,
            'locale' => 'fr',
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'required',
            'tax_id_collection' => ['enabled' => true],
            'customer_update' => ['address' => 'auto', 'name' => 'auto'],
            'success_url' => route('billing.return').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.index').'?annule=1',
        ]);
        Audit::log('billing.checkout_started', $org, ['plan' => $plan->slug, 'interval' => $interval], $org->id);

        return $session['url'];
    }

    public function swap(Organization $org, SubscriptionPlan $plan, string $interval): void
    {
        $sub = $this->activeSubscription($org) ?? throw ValidationException::withMessages(['plan' => 'Aucun abonnement actif.']);
        $price = $plan->stripePriceFor($interval) ?? throw ValidationException::withMessages(['plan' => 'Tarif non configuré pour cette offre.']);
        $current = $this->stripe->retrieveSubscription($sub->stripe_subscription_id);
        $itemId = $current['items']['data'][0]['id'] ?? throw ValidationException::withMessages(['plan' => 'Abonnement Stripe incohérent.']);
        // Le changement est confirmé par le webhook customer.subscription.updated.
        $this->stripe->updateSubscription($sub->stripe_subscription_id, [
            'items' => [['id' => $itemId, 'price' => $price]],
            'proration_behavior' => 'create_prorations',
            'metadata' => ['organization_id' => (string) $org->id, 'plan_id' => (string) $plan->id],
        ]);
        Audit::log('billing.swap_requested', $org, ['plan' => $plan->slug, 'interval' => $interval], $org->id);
    }

    public function cancel(Organization $org): void
    {
        $sub = $this->activeSubscription($org) ?? throw ValidationException::withMessages(['plan' => 'Aucun abonnement actif.']);
        $this->stripe->updateSubscription($sub->stripe_subscription_id, ['cancel_at_period_end' => true]);
        Audit::log('billing.cancel_requested', $org, [], $org->id);
    }

    public function resume(Organization $org): void
    {
        $sub = $this->activeSubscription($org) ?? throw ValidationException::withMessages(['plan' => 'Aucun abonnement à reprendre.']);
        $this->stripe->updateSubscription($sub->stripe_subscription_id, ['cancel_at_period_end' => false]);
        Audit::log('billing.resume_requested', $org, [], $org->id);
    }

    // ------------------------------------------------------------------ Webhooks

    /** @return string processed|ignored */
    public function handleEvent(array $event): string
    {
        $object = $event['data']['object'] ?? [];

        return match ($event['type']) {
            'checkout.session.completed' => $this->onCheckoutCompleted($object),
            'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted',
            'customer.subscription.paused', 'customer.subscription.resumed' => $this->onSubscription($object, (int) $event['created'], $event['type']),
            'invoice.paid', 'invoice.payment_succeeded' => $this->onInvoice($object, 'paid'),
            'invoice.payment_failed' => $this->onInvoice($object, 'failed'),
            'invoice.voided' => $this->onInvoice($object, 'void'),
            'invoice.marked_uncollectible' => $this->onInvoice($object, 'uncollectible'),
            'charge.refunded' => $this->onRefund($object),
            default => 'ignored',
        };
    }

    private function onCheckoutCompleted(array $session): string
    {
        $orgId = (int) ($session['client_reference_id'] ?? 0);
        $org = Organization::find($orgId);
        if (! $org || empty($session['customer'])) {
            return 'ignored';
        }
        StripeCustomer::firstOrCreate(['organization_id' => $org->id], ['stripe_customer_id' => $session['customer'], 'email' => $session['customer_details']['email'] ?? null]);

        return 'processed';
    }

    private function onSubscription(array $s, int $eventCreated, string $type): string
    {
        $org = $this->organizationFor($s);
        if (! $org) {
            throw new \RuntimeException('Organisation introuvable pour l\'abonnement '.($s['id'] ?? '?'));
        }
        $item = $s['items']['data'][0] ?? [];
        $priceId = $item['price']['id'] ?? null;
        $plan = $this->planForPrice($priceId) ?? SubscriptionPlan::find((int) ($s['metadata']['plan_id'] ?? 0));
        if (! $plan) {
            throw new \RuntimeException('Offre introuvable pour le prix '.$priceId);
        }

        return DB::transaction(function () use ($s, $org, $plan, $item, $priceId, $eventCreated, $type) {
            $sub = Subscription::lockForUpdate()->firstWhere('stripe_subscription_id', $s['id']);
            // Événements reçus dans le désordre : on ignore un état plus ancien que celui déjà appliqué.
            if ($sub && $sub->last_stripe_event_created && $eventCreated < $sub->last_stripe_event_created) {
                return 'ignored';
            }
            $periodEnd = $s['current_period_end'] ?? $item['current_period_end'] ?? null;
            $periodStart = $s['current_period_start'] ?? $item['current_period_start'] ?? null;
            $status = $type === 'customer.subscription.deleted' ? 'canceled' : ($s['status'] ?? 'incomplete');

            $sub ??= new Subscription(['stripe_subscription_id' => $s['id']]);
            $wasStatus = $sub->stripe_status;
            $wasPlan = $sub->subscription_plan_id;
            $sub->fill([
                'organization_id' => $org->id,
                'subscription_plan_id' => $plan->id,
                'stripe_price_id' => $priceId,
                'interval' => ($item['price']['recurring']['interval'] ?? 'month') === 'year' ? 'year' : 'month',
                'unit_amount' => isset($item['price']['unit_amount']) ? $item['price']['unit_amount'] / 100 : $sub->unit_amount,
                'currency' => strtoupper($item['price']['currency'] ?? 'eur'),
                'stripe_status' => $status,
                'trial_ends_at' => isset($s['trial_end']) ? Carbon::createFromTimestamp($s['trial_end']) : null,
                'current_period_start' => $periodStart ? Carbon::createFromTimestamp($periodStart) : null,
                'current_period_end' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
                'cancel_at_period_end' => (bool) ($s['cancel_at_period_end'] ?? false),
                'canceled_at' => isset($s['canceled_at']) ? Carbon::createFromTimestamp($s['canceled_at']) : null,
                'ended_at' => isset($s['ended_at']) ? Carbon::createFromTimestamp($s['ended_at']) : null,
                'last_stripe_event_created' => $eventCreated,
            ]);
            $sub->save();

            $this->syncLicense($sub);
            $this->notifySubscriptionChange($org, $sub, $wasStatus, $wasPlan);

            return 'processed';
        });
    }

    /** Convertit l'état Stripe en état de licence applicative. */
    public function syncLicense(Subscription $sub): License
    {
        $status = match ($sub->stripe_status) {
            'trialing', 'active' => $sub->cancel_at_period_end ? 'cancel_scheduled' : 'active',
            'past_due' => 'grace',
            'unpaid', 'paused' => 'suspended',
            'incomplete' => 'pending',
            default => 'expired', // canceled, incomplete_expired
        };
        $license = License::firstOrNew(['subscription_id' => $sub->id]);
        $graceEnds = $status === 'grace' ? ($license->status === 'grace' && $license->grace_ends_at ? $license->grace_ends_at : now()->addDays(config('equine.billing.grace_days'))) : null;
        // La grâce expirée bascule en suspension (lecture seule) même sans nouvel événement.
        if ($status === 'grace' && $graceEnds->isPast()) {
            $status = 'suspended';
        }
        $license->fill([
            'organization_id' => $sub->organization_id,
            'subscription_plan_id' => $sub->subscription_plan_id,
            'source' => 'stripe',
            'status' => $status,
            'starts_at' => $license->starts_at ?? $sub->current_period_start ?? now(),
            'ends_at' => in_array($status, ['expired'], true) ? ($sub->ended_at ?? now()) : $sub->current_period_end,
            'grace_ends_at' => $graceEnds,
        ])->save();
        $this->entitlements->flush($sub->organization);

        return $license;
    }

    private function onInvoice(array $inv, string $status): string
    {
        $org = $this->organizationFor($inv);
        if (! $org || empty($inv['id'])) {
            return 'ignored';
        }
        $subId = $inv['subscription'] ?? ($inv['parent']['subscription_details']['subscription'] ?? null);
        $sub = $subId ? Subscription::firstWhere('stripe_subscription_id', $subId) : null;
        $record = PaymentRecord::firstOrNew(['stripe_invoice_id' => $inv['id']]);
        $alreadyPaid = $record->exists && $record->status === 'paid';
        $line = $inv['lines']['data'][0]['period'] ?? [];
        $record->fill([
            'organization_id' => $org->id,
            'subscription_id' => $sub?->id,
            'stripe_payment_intent_id' => is_string($inv['payment_intent'] ?? null) ? $inv['payment_intent'] : null,
            'number' => $inv['number'] ?? null,
            'amount_due' => ($inv['amount_due'] ?? 0) / 100,
            'amount_paid' => ($inv['amount_paid'] ?? 0) / 100,
            'currency' => strtoupper($inv['currency'] ?? 'eur'),
            'status' => $status,
            'hosted_invoice_url' => $inv['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $inv['invoice_pdf'] ?? null,
            'period_start' => isset($line['start']) ? Carbon::createFromTimestamp($line['start']) : null,
            'period_end' => isset($line['end']) ? Carbon::createFromTimestamp($line['end']) : null,
            'paid_at' => $status === 'paid' ? Carbon::createFromTimestamp($inv['status_transitions']['paid_at'] ?? time()) : $record->paid_at,
            'failure_message' => $status === 'failed' ? ($inv['last_finalization_error']['message'] ?? 'Paiement refusé') : null,
        ])->save();

        if ($status === 'paid' && ! $alreadyPaid && ($inv['amount_paid'] ?? 0) > 0) {
            $renewal = ($inv['billing_reason'] ?? '') === 'subscription_cycle';
            $this->notifyBilling($org, $renewal ? 'renewal' : 'payment_succeeded', $renewal ? 'Abonnement renouvelé' : 'Paiement confirmé',
                'Paiement de '.number_format(($inv['amount_paid'] ?? 0) / 100, 2, ',', ' ').' '.strtoupper($inv['currency'] ?? 'EUR').' reçu pour '.$org->name.'.');
        }
        if ($status === 'failed') {
            $this->notifyBilling($org, 'payment_failed', 'Échec de paiement', 'Le paiement de l\'abonnement de '.$org->name.' a échoué. Mettez à jour votre moyen de paiement pour éviter la suspension (période de grâce de '.config('equine.billing.grace_days').' jours).');
        }

        return 'processed';
    }

    private function onRefund(array $charge): string
    {
        $invoiceId = $charge['invoice'] ?? null;
        $record = $invoiceId ? PaymentRecord::firstWhere('stripe_invoice_id', $invoiceId) : null;
        if (! $record) {
            return 'ignored';
        }
        $refunded = ($charge['amount_refunded'] ?? 0) / 100;
        $record->update(['amount_refunded' => $refunded, 'status' => $refunded >= (float) $record->amount_paid ? 'refunded' : $record->status]);

        return 'processed';
    }

    private function organizationFor(array $object): ?Organization
    {
        if (! empty($object['metadata']['organization_id'])) {
            return Organization::find((int) $object['metadata']['organization_id']);
        }
        if (! empty($object['subscription_details']['metadata']['organization_id'])) {
            return Organization::find((int) $object['subscription_details']['metadata']['organization_id']);
        }
        $customer = is_string($object['customer'] ?? null) ? $object['customer'] : null;

        return $customer ? StripeCustomer::firstWhere('stripe_customer_id', $customer)?->organization : null;
    }

    /** Retrouve l'offre d'un prix Stripe, y compris d'anciens prix conservés après une évolution tarifaire. */
    public function planForPrice(?string $priceId): ?SubscriptionPlan
    {
        if (! $priceId) {
            return null;
        }

        return SubscriptionPlan::where('stripe_price_monthly_id', $priceId)->orWhere('stripe_price_yearly_id', $priceId)->first()
            ?? PlanPriceChange::where('old_stripe_price_monthly_id', $priceId)->orWhere('old_stripe_price_yearly_id', $priceId)->first()?->plan;
    }

    private function notifySubscriptionChange(Organization $org, Subscription $sub, ?string $wasStatus, ?int $wasPlan): void
    {
        if ($wasStatus !== 'canceled' && $sub->stripe_status === 'canceled') {
            $this->notifyBilling($org, 'cancellation', 'Abonnement terminé', 'L\'abonnement de '.$org->name.' est terminé. Vos données sont conservées en lecture seule et restent exportables pendant '.config('equine.billing.recovery_days').' jours au minimum.');
        } elseif ($sub->wasChanged('cancel_at_period_end') && $sub->cancel_at_period_end) {
            $this->notifyBilling($org, 'cancellation', 'Résiliation programmée', 'L\'abonnement de '.$org->name.' prendra fin le '.$sub->current_period_end?->format('d/m/Y').'.');
        }
    }

    private function notifyBilling(Organization $org, string $kind, string $title, string $body): void
    {
        foreach ($org->members()->with(['user', 'role'])->get() as $m) {
            if (in_array(Perm::BILLING_MANAGE, $m->role->permissionKeys(), true)) {
                $m->user->notify(new AppNotification($kind, $title, $body, route('billing.index')));
            }
        }
    }
}
