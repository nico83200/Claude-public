<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\PaymentRecord;
use App\Models\StripeCustomer;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Billing\StripeGateway;
use App\Services\Entitlements;
use App\Services\OrganizationService;
use Mockery;
use Tests\TestCase;

class BillingTest extends TestCase
{
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = SubscriptionPlan::where('slug', 'particulier')->first();
        $this->plan->update(['stripe_price_monthly_id' => 'price_month_test', 'stripe_price_yearly_id' => 'price_year_test']);
    }

    private function webhook(array $event)
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', "$t.$payload", 'whsec_test_secret');

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=$sig", 'CONTENT_TYPE' => 'application/json'], $payload);
    }

    private function subscriptionEvent(int $orgId, string $status, int $created, array $extra = [], string $type = 'customer.subscription.updated', ?string $id = null): array
    {
        return [
            'id' => $id ?? 'evt_'.uniqid(), 'object' => 'event', 'type' => $type, 'created' => $created, 'livemode' => false,
            'data' => ['object' => array_merge([
                'id' => 'sub_123', 'object' => 'subscription', 'customer' => 'cus_123', 'status' => $status,
                'metadata' => ['organization_id' => (string) $orgId, 'plan_id' => (string) $this->plan->id],
                'cancel_at_period_end' => false,
                'items' => ['data' => [['id' => 'si_1', 'current_period_start' => now()->timestamp, 'current_period_end' => now()->addMonth()->timestamp, 'price' => ['id' => 'price_month_test', 'unit_amount' => 690, 'currency' => 'eur', 'recurring' => ['interval' => 'month']]]]],
            ], $extra)],
        ];
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad', 'CONTENT_TYPE' => 'application/json'], '{"id":"evt_x"}')->assertStatus(400);
        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_subscription_webhook_activates_license_and_is_idempotent(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();
        $event = $this->subscriptionEvent($org->id, 'active', time(), [], 'customer.subscription.created', 'evt_same');

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, StripeEvent::count());
        $this->assertSame(1, Subscription::count());
        $sub = Subscription::first();
        $this->assertSame('6.90', $sub->unit_amount);
        $license = License::where('subscription_id', $sub->id)->firstOrFail();
        $this->assertSame('active', $license->status);
        $ent = app(Entitlements::class)->for($org->fresh());
        $this->assertSame('particulier', $ent['plan']->slug);
        $this->assertTrue($ent['writable']);
        $this->assertSame(3, $ent['limits']['max_horses']);
    }

    public function test_out_of_order_events_are_ignored(): void
    {
        $org = $this->user()->personalOrganization();
        $this->webhook($this->subscriptionEvent($org->id, 'canceled', time(), [], 'customer.subscription.deleted'))->assertOk();
        $this->webhook($this->subscriptionEvent($org->id, 'active', time() - 3600))->assertOk();
        $this->assertSame('canceled', Subscription::first()->stripe_status);
        $this->assertSame('ignored', StripeEvent::latest('id')->first()->status);
        $this->assertSame('expired', License::first()->status);
    }

    public function test_payment_failure_grace_then_suspension_and_recovery(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();
        $horse = $this->horseFor($user);
        StripeCustomer::create(['organization_id' => $org->id, 'stripe_customer_id' => 'cus_123']);
        $this->webhook($this->subscriptionEvent($org->id, 'active', time() - 100));

        $this->webhook(['id' => 'evt_inv_fail', 'type' => 'invoice.payment_failed', 'created' => time(), 'data' => ['object' => ['id' => 'in_1', 'customer' => 'cus_123', 'subscription' => 'sub_123', 'amount_due' => 690, 'amount_paid' => 0, 'currency' => 'eur']]])->assertOk();
        $this->webhook($this->subscriptionEvent($org->id, 'past_due', time()))->assertOk();
        $this->assertSame('failed', PaymentRecord::first()->status);
        $license = License::first();
        $this->assertSame('grace', $license->status);
        $this->assertTrue(app(Entitlements::class)->writable($org->fresh()));

        // Fin de grâce sans paiement → lecture seule, données conservées
        $license->update(['grace_ends_at' => now()->subMinute()]);
        $this->artisan('licenses:refresh')->assertSuccessful();
        $this->assertSame('suspended', $license->fresh()->status);
        app(Entitlements::class)->flush();
        $this->actingAs($user)->put("/chevaux/{$horse->id}", ['official_name' => 'X', 'sex' => 'male'])->assertForbidden();
        $this->actingAs($user)->get("/chevaux/{$horse->id}")->assertOk();

        // Paiement régularisé
        $this->webhook(['id' => 'evt_inv_ok', 'type' => 'invoice.paid', 'created' => time() + 10, 'data' => ['object' => ['id' => 'in_1', 'customer' => 'cus_123', 'subscription' => 'sub_123', 'amount_due' => 690, 'amount_paid' => 690, 'currency' => 'eur', 'status_transitions' => ['paid_at' => time()]]]])->assertOk();
        $this->webhook($this->subscriptionEvent($org->id, 'active', time() + 20))->assertOk();
        $this->assertSame('paid', PaymentRecord::first()->status);
        $this->assertSame('active', $license->fresh()->status);
    }

    public function test_cancellation_scheduled_then_expired(): void
    {
        $org = $this->user()->personalOrganization();
        $this->webhook($this->subscriptionEvent($org->id, 'active', time() - 100, ['cancel_at_period_end' => true]))->assertOk();
        $this->assertSame('cancel_scheduled', License::first()->status);
        $this->webhook($this->subscriptionEvent($org->id, 'canceled', time(), ['ended_at' => time()], 'customer.subscription.deleted'))->assertOk();
        $this->assertSame('expired', License::first()->status);
        $this->assertFalse(app(Entitlements::class)->for($org->fresh())['writable']);
    }

    public function test_refund_updates_payment_record(): void
    {
        $org = $this->user()->personalOrganization();
        StripeCustomer::create(['organization_id' => $org->id, 'stripe_customer_id' => 'cus_123']);
        $this->webhook(['id' => 'evt_p', 'type' => 'invoice.paid', 'created' => time(), 'data' => ['object' => ['id' => 'in_9', 'customer' => 'cus_123', 'amount_due' => 6900, 'amount_paid' => 6900, 'currency' => 'eur']]]);
        $this->webhook(['id' => 'evt_r', 'type' => 'charge.refunded', 'created' => time(), 'data' => ['object' => ['id' => 'ch_1', 'invoice' => 'in_9', 'amount_refunded' => 6900]]]);
        $this->assertSame('refunded', PaymentRecord::first()->status);
    }

    public function test_unprocessable_event_is_marked_failed_and_can_be_reprocessed(): void
    {
        // Abonnement pour une organisation inconnue : échec → Stripe réessaiera (500)
        $this->webhook($this->subscriptionEvent(999999, 'active', time(), [], 'customer.subscription.created', 'evt_fail'))->assertStatus(500);
        $event = StripeEvent::firstOrFail();
        $this->assertSame('failed', $event->status);
        $this->assertSame(1, $event->attempts);

        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post("/admin/facturation/evenements/{$event->id}/retraiter")->assertSessionHas('error');
        $this->assertSame(2, $event->fresh()->attempts);
    }

    public function test_checkout_redirects_to_stripe_and_success_page_does_not_activate(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('createCustomer')->once()->andReturn('cus_new');
        $gateway->shouldReceive('createCheckoutSession')->once()->withArgs(function ($params) {
            return $params['mode'] === 'subscription' && $params['line_items'][0]['price'] === 'price_year_test'
                && $params['subscription_data']['trial_period_days'] === 14 && str_contains($params['success_url'], '{CHECKOUT_SESSION_ID}');
        })->andReturn(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1']);
        $this->app->instance(StripeGateway::class, $gateway);

        $user = $this->user();
        $this->actingAs($user)->get('/abonnement')->assertOk()->assertSee('Particulier');
        $this->actingAs($user)->post('/abonnement/souscrire', ['plan_id' => $this->plan->id, 'interval' => 'year', 'accept' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');
        $this->assertDatabaseHas('stripe_customers', ['stripe_customer_id' => 'cus_new']);

        // Session relue chez Stripe : non payée → rien n'est activé, quel que soit le contenu de l'URL.
        $gateway->shouldReceive('retrieveCheckoutSession')->with('cs_1')->andReturn(['id' => 'cs_1', 'status' => 'open', 'client_reference_id' => (string) $user->personalOrganization()->id, 'subscription' => null]);
        $this->actingAs($user)->get('/abonnement/retour?session_id=cs_1')->assertRedirect('/abonnement');
        $this->assertSame(0, License::count());
    }

    public function test_checkout_requires_terms_acceptance_and_billing_permission(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $user = $this->user();
        $this->actingAs($user)->post('/abonnement/souscrire', ['plan_id' => $this->plan->id, 'interval' => 'month'])->assertSessionHasErrors('accept');

        $stableOwner = $this->user();
        $stable = $this->stable($stableOwner);
        $staff = $this->user();
        app(OrganizationService::class)->addMember($stable, $staff, 'manager');
        $staff->forceFill(['current_organization_id' => $stable->id])->save();
        $this->actingAs($staff)->get('/abonnement')->assertForbidden();
    }

    public function test_swap_and_cancel_go_through_stripe_and_apply_its_response(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();
        $this->webhook($this->subscriptionEvent($org->id, 'active', time() - 10));
        $multi = SubscriptionPlan::where('slug', 'multi-chevaux')->first();
        $multi->update(['stripe_price_monthly_id' => 'price_multi']);
        $current = $this->stripeSubscription($org->id, 'active');
        $swapped = $current;
        $swapped['items']['data'][0]['price'] = ['id' => 'price_multi', 'unit_amount' => 1290, 'currency' => 'eur', 'recurring' => ['interval' => 'month']];
        $gateway = Mockery::mock(StripeGateway::class);
        $gateway->shouldReceive('configured')->andReturn(true);
        // Faux Stripe : garde l'état de l'abonnement entre la modification et les relectures.
        $state = $current;
        $gateway->shouldReceive('retrieveSubscription')->andReturnUsing(function () use (&$state) {
            return $state;
        });
        $gateway->shouldReceive('updateSubscription')->with('sub_123', Mockery::on(fn ($p) => ($p['items'][0]['price'] ?? null) === 'price_multi'))->once()->andReturnUsing(function () use (&$state, $swapped) {
            return $state = $swapped;
        });
        $gateway->shouldReceive('updateSubscription')->with('sub_123', ['cancel_at_period_end' => true])->once()->andReturnUsing(function () use (&$state) {
            return $state = ['cancel_at_period_end' => true] + $state;
        });
        $gateway->shouldReceive('updateSubscription')->with('sub_123', ['cancel_at_period_end' => false])->once()->andReturnUsing(function () use (&$state) {
            return $state = ['cancel_at_period_end' => false] + $state;
        });
        $this->app->instance(StripeGateway::class, $gateway);

        $this->actingAs($user)->post('/abonnement/changer', ['plan_id' => $multi->id, 'interval' => 'month'])->assertSessionHas('success');
        $this->assertSame($multi->id, Subscription::first()->subscription_plan_id);

        // Résiliation : visible tout de suite (plus de bouton « Résilier », date de fin au lieu du renouvellement).
        $this->actingAs($user)->post('/abonnement/resilier')->assertSessionHas('success');
        $this->assertTrue(Subscription::first()->cancel_at_period_end);
        $this->assertSame('cancel_scheduled', License::first()->status);
        $this->actingAs($user)->get('/abonnement')->assertSee('Se termine le')->assertSee('Reprendre l\'abonnement', false)
            ->assertDontSee('Renouvellement le')->assertDontSee('>Résilier</button>', false);

        // Un webhook plus ancien que la réponse de l'API ne rétablit pas l'ancien état.
        $this->webhook($this->subscriptionEvent($org->id, 'active', time() - 5))->assertOk();
        $this->assertTrue(Subscription::first()->cancel_at_period_end);

        $this->actingAs($user)->post('/abonnement/reprendre')->assertSessionHas('success');
        $this->assertFalse(Subscription::first()->cancel_at_period_end);
    }

    public function test_missed_webhooks_are_caught_up_from_stripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $user = $this->user();
        $org = $user->personalOrganization();
        $this->webhook($this->subscriptionEvent($org->id, 'trialing', time() - 10));
        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('retrieveSubscription')->with('sub_123')->andReturn(['cancel_at_period_end' => true] + $this->stripeSubscription($org->id, 'active'), $this->stripeSubscription($org->id, 'canceled'));
        $this->app->instance(StripeGateway::class, $gateway);

        // Résiliation faite dans le portail Stripe : relue au retour sur la page Abonnement.
        $this->actingAs($user)->get('/abonnement?maj=1')->assertOk()->assertSee('Se termine le');
        $this->assertTrue(Subscription::first()->cancel_at_period_end);

        // Fin effective sans webhook : rattrapée par la tâche planifiée.
        $this->artisan('billing:sync')->assertSuccessful();
        $this->assertSame('canceled', Subscription::first()->stripe_status);
        $this->assertSame('expired', License::first()->status);
    }

    public function test_old_prices_still_map_to_plan_after_price_change(): void
    {
        $user = $this->user();
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->put("/admin/offres/{$this->plan->id}", [
            'name' => $this->plan->name, 'currency' => 'EUR', 'audience' => 'individual', 'price_monthly' => '7.90', 'price_yearly' => '69.00',
            'existing_subscribers' => 'keep_old_price', 'features' => $this->plan->features, 'is_active' => '1', 'is_public' => '1', 'max_horses' => 3,
        ])->assertRedirect();
        $this->assertNull($this->plan->fresh()->stripe_price_monthly_id);
        $this->assertDatabaseHas('plan_price_changes', ['subscription_plan_id' => $this->plan->id, 'old_stripe_price_monthly_id' => 'price_month_test']);

        // Un abonné existant sur l'ancien prix reste rattaché à l'offre (retrouvé via son client Stripe).
        StripeCustomer::create(['organization_id' => $user->personalOrganization()->id, 'stripe_customer_id' => 'cus_123']);
        $this->webhook($this->subscriptionEvent($user->personalOrganization()->id, 'active', time(), ['metadata' => []]))->assertOk();
        $this->assertSame($this->plan->id, Subscription::first()->subscription_plan_id);
        $this->assertSame('6.90', Subscription::first()->unit_amount);
    }

    private function stripeSubscription(int $orgId, string $status = 'trialing'): array
    {
        return $this->subscriptionEvent($orgId, $status, time(), ['trial_end' => now()->addDays(14)->timestamp])['data']['object'];
    }

    public function test_return_from_checkout_activates_subscription_read_from_stripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $user = $this->user();
        $org = $user->personalOrganization();
        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('retrieveCheckoutSession')->with('cs_ok')->andReturn(['id' => 'cs_ok', 'status' => 'complete', 'client_reference_id' => (string) $org->id, 'subscription' => 'sub_123']);
        $gateway->shouldReceive('retrieveSubscription')->with('sub_123')->andReturn($this->stripeSubscription($org->id));
        $this->app->instance(StripeGateway::class, $gateway);

        $this->actingAs($user)->get('/abonnement/retour?session_id=cs_ok')->assertRedirect('/abonnement')->assertSessionHas('success');
        $this->assertSame('trialing', Subscription::first()->stripe_status);
        $ent = app(Entitlements::class)->for($org->fresh());
        $this->assertSame('particulier', $ent['plan']->slug);
        $this->assertSame('active', $ent['status']);
        $this->actingAs($user)->get('/abonnement')->assertSee('Essai jusqu', false);
    }

    public function test_return_with_session_of_another_space_activates_nothing(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $user = $this->user();
        $other = $this->user();
        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('retrieveCheckoutSession')->andReturn(['id' => 'cs_x', 'status' => 'complete', 'client_reference_id' => (string) $other->personalOrganization()->id, 'subscription' => 'sub_123']);
        $gateway->shouldNotReceive('retrieveSubscription');
        $this->app->instance(StripeGateway::class, $gateway);

        $this->actingAs($user)->get('/abonnement/retour?session_id=cs_x')->assertRedirect('/abonnement');
        $this->assertSame(0, Subscription::count());
        $this->assertSame(0, License::count());
    }

    /** Cas réel : seuls les événements de facture arrivent (abonnement non reçu ou pas encore reçu). */
    public function test_invoice_for_unknown_subscription_syncs_it_from_stripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_x']);
        $user = $this->user();
        $org = $user->personalOrganization();
        StripeCustomer::create(['organization_id' => $org->id, 'stripe_customer_id' => 'cus_123']);
        $gateway = Mockery::mock(StripeGateway::class)->makePartial();
        $gateway->shouldReceive('retrieveSubscription')->with('sub_123')->once()->andReturn($this->stripeSubscription($org->id));
        $this->app->instance(StripeGateway::class, $gateway);

        $this->webhook(['id' => 'evt_inv0', 'object' => 'event', 'type' => 'invoice.paid', 'created' => time(), 'livemode' => false, 'data' => ['object' => [
            'id' => 'in_0', 'object' => 'invoice', 'customer' => 'cus_123', 'amount_due' => 0, 'amount_paid' => 0, 'currency' => 'eur', 'billing_reason' => 'subscription_create',
            'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_123', 'metadata' => []]],
        ]]])->assertOk();

        $this->assertSame(Subscription::first()->id, PaymentRecord::first()->subscription_id);
        $this->assertSame('particulier', app(Entitlements::class)->for($org->fresh())['plan']->slug);

        // L'événement d'abonnement arrivé ensuite (plus ancien que la relecture) ne dégrade rien.
        $this->webhook($this->subscriptionEvent($org->id, 'incomplete', time() - 60, [], 'customer.subscription.created'))->assertOk();
        $this->assertSame('trialing', Subscription::first()->stripe_status);
    }
}
