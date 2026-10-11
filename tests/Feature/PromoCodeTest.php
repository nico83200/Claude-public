<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\StripeGateway;
use App\Services\Entitlements;
use App\Services\OrganizationService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    private function admin(): User
    {
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function asAdmin(User $admin)
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function freeCode(array $attrs = []): PromoCode
    {
        return PromoCode::create($attrs + [
            'code' => 'BIENVENUE', 'type' => 'free_access', 'label' => 'Test',
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'multi-chevaux')->value('id'), 'free_days' => 30,
        ]);
    }

    public function test_admin_creates_free_access_code_without_stripe(): void
    {
        $plan = SubscriptionPlan::where('slug', 'multi-chevaux')->first();
        $this->asAdmin($this->admin())->post('/admin/codes', [
            'code' => 'salon-2026', 'type' => 'free_access', 'label' => 'Salon', 'subscription_plan_id' => $plan->id, 'free_days' => 60, 'max_redemptions' => 2,
        ])->assertRedirect();
        $promo = PromoCode::firstWhere('code', 'SALON-2026');
        $this->assertNotNull($promo);
        $this->assertSame(60, $promo->free_days);
        $this->assertNull($promo->stripe_promotion_code_id);
        $admin = User::where('is_super_admin', true)->first();
        $this->asAdmin($admin)->get('/admin/codes')->assertOk()->assertSee('SALON-2026');
        $this->asAdmin($admin)->get('/admin/codes/'.$promo->id)->assertOk()->assertSee('offert pendant 60 jours')->assertSee('Pas encore utilisé');
    }

    public function test_regular_user_cannot_manage_codes(): void
    {
        $this->actingAs($this->user())->withSession(['auth.password_confirmed_at' => time()])->get('/admin/codes')->assertNotFound();
    }

    public function test_free_access_code_grants_license_for_duration_then_expires(): void
    {
        $promo = $this->freeCode(['max_redemptions' => 1]);
        $user = $this->user();
        $org = $user->personalOrganization();

        $this->actingAs($user)->post('/abonnement/code', ['code' => ' bienvenue '])->assertSessionHasNoErrors();
        $license = License::where('organization_id', $org->id)->where('source', 'code')->first();
        $this->assertNotNull($license);
        $this->assertSame('active', $license->status);
        $this->assertTrue($license->ends_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertSame(1, $promo->fresh()->redemptions_count);
        $ent = app(Entitlements::class)->for($org->fresh());
        $this->assertSame('multi-chevaux', $ent['plan']->slug);
        $this->assertTrue($ent['writable']);
        $this->actingAs($user)->get('/abonnement')->assertOk()->assertSee('Accès offert')->assertSee($license->ends_at->format('d/m/Y'));

        // Une seule utilisation par espace, et limite globale atteinte
        $this->actingAs($user)->post('/abonnement/code', ['code' => 'BIENVENUE'])->assertSessionHasErrors('code');
        $other = $this->user();
        $this->actingAs($other)->post('/abonnement/code', ['code' => 'BIENVENUE'])->assertSessionHasErrors(['code' => 'Ce code a atteint son nombre maximal d\'utilisations.']);

        // À l'échéance, licenses:refresh expire la licence
        $this->travel(31)->days();
        Artisan::call('licenses:refresh');
        $this->assertSame('expired', $license->fresh()->status);
        // L'espace n'est pas bloqué : il revient à l'offre gratuite.
        app(Entitlements::class)->flush();
        $ent = app(Entitlements::class)->for($license->organization);
        $this->assertSame('decouverte', $ent['plan']->slug);
        $this->assertTrue($ent['writable']);
    }

    public function test_invalid_codes_are_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/abonnement/code', ['code' => 'NEXISTEPAS'])->assertSessionHasErrors('code');
        $this->freeCode(['code' => 'EXPIRE', 'expires_at' => now()->subDay()]);
        $this->actingAs($user)->post('/abonnement/code', ['code' => 'EXPIRE'])->assertSessionHasErrors(['code' => 'Ce code a expiré.']);
        $this->freeCode(['code' => 'OFF', 'is_active' => false]);
        $this->actingAs($user)->post('/abonnement/code', ['code' => 'OFF'])->assertSessionHasErrors('code');
        PromoCode::create(['code' => 'REMISE', 'type' => 'discount', 'label' => 'x', 'percent_off' => 20, 'duration' => 'once', 'stripe_promotion_code_id' => 'promo_1']);
        $this->actingAs($user)->post('/abonnement/code', ['code' => 'REMISE'])->assertSessionHasErrors('code');
        $this->assertSame(0, License::where('source', 'code')->count());
    }

    public function test_code_requires_billing_permission(): void
    {
        $this->freeCode();
        $owner = $this->user();
        $stable = $this->stable($owner);
        $member = $this->user();
        app(OrganizationService::class)->addMember($stable, $member, 'manager');
        $member->forceFill(['current_organization_id' => $stable->id])->save();
        $this->actingAs($member)->post('/abonnement/code', ['code' => 'BIENVENUE'])->assertForbidden();
    }

    public function test_admin_creates_discount_code_in_stripe(): void
    {
        $plan = SubscriptionPlan::where('slug', 'particulier')->first();
        $plan->update(['stripe_product_id' => 'prod_part']);
        $gateway = Mockery::mock(StripeGateway::class);
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('createCoupon')->once()->withArgs(fn ($p) => $p['percent_off'] == 20 && $p['duration'] === 'repeating'
            && $p['duration_in_months'] === 3 && $p['applies_to']['products'] === ['prod_part'])->andReturn('coupon_1');
        $gateway->shouldReceive('createPromotionCode')->once()->withArgs(fn ($c, $code, $p) => $c === 'coupon_1' && $code === 'PRINTEMPS' && $p['max_redemptions'] === 50)->andReturn('promo_1');
        $gateway->shouldReceive('setPromotionCodeActive')->once()->with('promo_1', false);
        $this->app->instance(StripeGateway::class, $gateway);

        $admin = $this->admin();
        $this->asAdmin($admin)->post('/admin/codes', [
            'code' => 'printemps', 'type' => 'discount', 'label' => 'Printemps', 'discount_kind' => 'percent', 'percent_off' => 20,
            'duration' => 'repeating', 'duration_months' => 3, 'plan_ids' => [$plan->id], 'max_redemptions' => 50,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $promo = PromoCode::firstWhere('code', 'PRINTEMPS');
        $this->assertSame('promo_1', $promo->stripe_promotion_code_id);
        $this->assertSame([$plan->id], $promo->plan_ids);
        $this->assertNull($promo->amount_off);

        $this->asAdmin($admin)->patch('/admin/codes/'.$promo->id, ['is_active' => 0])->assertRedirect();
        $this->assertFalse($promo->fresh()->is_active);
    }

    public function test_discount_code_is_preapplied_to_checkout_and_recorded_by_webhook(): void
    {
        $plan = SubscriptionPlan::where('slug', 'particulier')->first();
        $plan->update(['stripe_price_monthly_id' => 'price_m', 'stripe_price_yearly_id' => 'price_y']);
        $promo = PromoCode::create(['code' => 'REMISE20', 'type' => 'discount', 'label' => 'x', 'percent_off' => 20, 'duration' => 'once', 'stripe_promotion_code_id' => 'promo_abc', 'plan_ids' => [$plan->id]]);
        config(['services.stripe.secret' => 'sk_test_x']);
        $gateway = Mockery::mock(StripeGateway::class);
        $gateway->shouldReceive('configured')->andReturn(true);
        $gateway->shouldReceive('createCustomer')->andReturn('cus_9');
        $gateway->shouldReceive('createCheckoutSession')->once()->withArgs(fn ($p) => $p['discounts'] === [['promotion_code' => 'promo_abc']]
            && ! isset($p['allow_promotion_codes']) && $p['metadata']['promo_code_id'] === (string) $promo->id)
            ->andReturn(['id' => 'cs_9', 'url' => 'https://checkout.stripe.com/c/pay/cs_9']);
        $this->app->instance(StripeGateway::class, $gateway);

        $user = $this->user();
        $org = $user->personalOrganization();
        // Code non applicable à une autre offre
        $other = SubscriptionPlan::where('slug', 'multi-chevaux')->first();
        $other->update(['stripe_price_monthly_id' => 'price_mc']);
        $this->actingAs($user)->post('/abonnement/souscrire', ['plan_id' => $other->id, 'interval' => 'month', 'accept' => '1', 'promo_code' => 'remise20'])->assertSessionHasErrors('promo_code');

        $this->actingAs($user)->post('/abonnement/souscrire', ['plan_id' => $plan->id, 'interval' => 'month', 'accept' => '1', 'promo_code' => 'remise20'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_9');

        $payload = json_encode(['id' => 'evt_cs9', 'object' => 'event', 'type' => 'checkout.session.completed', 'created' => time(), 'livemode' => false,
            'data' => ['object' => ['id' => 'cs_9', 'object' => 'checkout.session', 'customer' => 'cus_9', 'client_reference_id' => (string) $org->id,
                'metadata' => ['organization_id' => (string) $org->id, 'user_id' => (string) $user->id, 'promo_code_id' => (string) $promo->id]]]]);
        $t = time();
        $sig = hash_hmac('sha256', "$t.$payload", 'whsec_test_secret');
        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=$sig", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();
        $this->assertDatabaseHas('promo_code_redemptions', ['promo_code_id' => $promo->id, 'organization_id' => $org->id, 'stripe_checkout_session_id' => 'cs_9']);
        $this->assertSame(1, $promo->fresh()->redemptions_count);
    }

    public function test_admin_grants_free_access_for_a_number_of_days(): void
    {
        $user = $this->user();
        $org = $user->personalOrganization();
        $plan = SubscriptionPlan::where('slug', 'multi-chevaux')->first();
        $this->asAdmin($this->admin())->post('/admin/organisations/'.$org->id.'/licences', [
            'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->toDateString(), 'duration_days' => 90, 'notes' => 'Partenariat',
        ])->assertSessionHasNoErrors();
        $license = License::where('organization_id', $org->id)->where('source', 'manual')->first();
        $this->assertSame(90, (int) round($license->starts_at->diffInDays($license->ends_at)));
    }
}
