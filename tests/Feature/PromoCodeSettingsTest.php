<?php

namespace Tests\Feature;

use App\Models\AcquisitionSource;
use App\Models\AcquisitionVisit;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\StripeGateway;
use Mockery;
use Tests\TestCase;

/** Codes promo : réglages modifiables après création, sources de provenance gérées dans l'administration. */
class PromoCodeSettingsTest extends TestCase
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

    public function test_free_access_code_settings_can_be_changed_after_creation(): void
    {
        $admin = $this->admin();
        $promo = PromoCode::create(['code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon', 'is_active' => true,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'), 'free_days' => 30, 'max_redemptions' => 10]);
        $promo->forceFill(['redemptions_count' => 4])->save();

        $this->asAdmin($admin)->get('/admin/codes/'.$promo->id)->assertOk()->assertSee('data-promo-settings', false);
        $this->asAdmin($admin)->put('/admin/codes/'.$promo->id.'/reglages', [
            'label' => 'Salon EquitaLyon', 'starts_at' => '2026-10-28', 'expires_at' => '2026-11-30', 'max_redemptions' => 500, 'first_subscription_only' => '1',
        ])->assertSessionHas('success');
        $promo->refresh();
        $this->assertSame(['Salon EquitaLyon', '2026-10-28', '2026-11-30', 500, true], [$promo->label, $promo->starts_at->toDateString(), $promo->expires_at->toDateString(), $promo->max_redemptions, $promo->first_subscription_only]);

        // Valable toute la journée de fin ; limite jamais inférieure aux utilisations déjà faites ; dates cohérentes.
        $this->travelTo(now()->setDate(2026, 11, 30)->setTime(18, 0));
        $this->assertNull($promo->fresh()->unavailableReason());
        $this->travelBack();
        $this->asAdmin($admin)->put('/admin/codes/'.$promo->id.'/reglages', ['label' => 'x', 'max_redemptions' => 3])->assertSessionHasErrors('max_redemptions');
        $this->asAdmin($admin)->put('/admin/codes/'.$promo->id.'/reglages', ['label' => 'x', 'starts_at' => '2026-12-01', 'expires_at' => '2026-11-01'])->assertSessionHasErrors('expires_at');
        // Vider les champs : illimité et sans date de fin, ouvert à tous.
        $this->asAdmin($admin)->put('/admin/codes/'.$promo->id.'/reglages', ['label' => 'Salon', 'starts_at' => '', 'expires_at' => '', 'max_redemptions' => ''])->assertSessionHas('success');
        $this->assertSame([null, null, false], [$promo->fresh()->max_redemptions, $promo->fresh()->expires_at, $promo->fresh()->first_subscription_only]);

        $this->actingAs($this->user())->put('/admin/codes/'.$promo->id.'/reglages', ['label' => 'pirate'])->assertNotFound();
    }

    public function test_stripe_discount_code_is_replaced_with_new_settings(): void
    {
        $stripe = Mockery::mock(StripeGateway::class);
        $this->app->instance(StripeGateway::class, $stripe);
        $promo = PromoCode::create(['code' => 'REMISE20', 'type' => 'discount', 'label' => 'x', 'percent_off' => 20, 'duration' => 'once', 'is_active' => true,
            'stripe_coupon_id' => 'co_1', 'stripe_promotion_code_id' => 'promo_old', 'max_redemptions' => 50]);
        $promo->forceFill(['redemptions_count' => 12])->save();

        $stripe->shouldReceive('setPromotionCodeActive')->once()->with('promo_old', false);
        $stripe->shouldReceive('createPromotionCode')->once()->withArgs(fn ($coupon, $code, $params) => $coupon === 'co_1' && $code === 'REMISE20'
            && $params['max_redemptions'] === 88 && $params['restrictions'] === ['first_time_transaction' => true] && $params['expires_at'] > time())->andReturn('promo_new');
        $this->asAdmin($this->admin())->put('/admin/codes/'.$promo->id.'/reglages', [
            'label' => 'Remise salon', 'expires_at' => now()->addMonth()->toDateString(), 'max_redemptions' => 100, 'first_subscription_only' => '1',
        ])->assertSessionHas('success');
        $this->assertSame('promo_new', $promo->fresh()->stripe_promotion_code_id);

        // Seul le libellé change : Stripe n'est pas sollicité.
        $this->asAdmin(User::where('is_super_admin', true)->first())->put('/admin/codes/'.$promo->id.'/reglages', [
            'label' => 'Remise salon 2', 'expires_at' => $promo->fresh()->expires_at->toDateString(), 'max_redemptions' => 100, 'first_subscription_only' => '1',
        ])->assertSessionHas('success');

        // Refus de Stripe : l'ancien code est réactivé, rien n'est modifié.
        $stripe->shouldReceive('setPromotionCodeActive')->once()->with('promo_new', false);
        $stripe->shouldReceive('createPromotionCode')->once()->andThrow(new \RuntimeException('Code déjà utilisé'));
        $stripe->shouldReceive('setPromotionCodeActive')->once()->with('promo_new', true);
        $this->asAdmin(User::where('is_super_admin', true)->first())->put('/admin/codes/'.$promo->id.'/reglages', ['label' => 'Remise salon 2', 'max_redemptions' => 200])
            ->assertSessionHas('error');
        $this->assertSame(100, $promo->fresh()->max_redemptions);
        $this->assertSame('promo_new', $promo->fresh()->stripe_promotion_code_id);
    }

    public function test_admin_adds_renames_and_removes_sources_with_links_and_qr_codes(): void
    {
        $admin = $this->admin();
        $promo = PromoCode::create(['code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon', 'is_active' => true,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'), 'free_days' => 30]);

        $this->asAdmin($admin)->get('/admin/codes')->assertOk()->assertSee('data-sources', false)->assertSee('Affiches (QR code)');
        $this->asAdmin($admin)->post('/admin/codes/sources', ['label' => 'Flyers sellerie Dupont'])->assertSessionHas('success');
        $flyer = AcquisitionSource::where('key', 'flyers-sellerie-dupont')->firstOrFail();
        $this->asAdmin($admin)->post('/admin/codes/sources', ['label' => 'Doublon', 'key' => 'Flyers Sellerie Dupont'])->assertSessionHasErrors('key');
        $this->asAdmin($admin)->post('/admin/codes/sources', ['label' => 'Réservé', 'key' => 'lien'])->assertSessionHasErrors('key');

        $this->asAdmin($admin)->get('/admin/codes/'.$promo->id)->assertOk()->assertSee('Flyers sellerie Dupont')
            ->assertSee('register?code=EQUITA2026&amp;src=flyers-sellerie-dupont', false);
        $qr = $this->asAdmin($admin)->get('/admin/codes/'.$promo->id.'/qr/flyers-sellerie-dupont')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $qr->getContent());
        $this->asAdmin($admin)->get('/admin/codes/'.$promo->id.'/qr/inconnue')->assertNotFound();

        // Les arrivées sont comptées sous le nom choisi ; renommer met à jour les statistiques.
        auth()->logout();
        $this->get('/register?code=EQUITA2026&src=flyers-sellerie-dupont')->assertOk();
        $this->asAdmin($admin)->patch('/admin/codes/sources/'.$flyer->id, ['label' => 'Flyers Dupont'])->assertSessionHas('success');
        $this->asAdmin($admin)->get('/admin/codes/'.$promo->id)->assertSee('Flyers Dupont');
        $this->asAdmin($admin)->delete('/admin/codes/sources/'.$flyer->id)->assertSessionHas('success');
        $this->assertSame(1, AcquisitionVisit::where('source', 'flyers-sellerie-dupont')->count());
        $this->asAdmin($admin)->get('/admin/statistiques')->assertSee('Flyers sellerie dupont (source non déclarée)');

        $this->actingAs($this->user())->post('/admin/codes/sources', ['label' => 'Pirate'])->assertNotFound();
    }

    public function test_qr_generator_with_optional_code_and_source(): void
    {
        $admin = $this->admin();
        PromoCode::create(['code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon', 'is_active' => true,
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'particulier')->value('id'), 'free_days' => 30]);

        $this->asAdmin($admin)->get('/admin/codes')->assertOk()->assertSee('data-qr-generator', false)->assertSee('Générateur de QR code')
            ->assertSee('EQUITA2026 · Salon')->assertSee('Instagram (story)');
        $svg = $this->asAdmin($admin)->get('/admin/codes/qr-code?code=EQUITA2026&src=affiche')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svg->getContent());
        $this->assertFalse($svg->headers->has('Content-Disposition'));
        $this->asAdmin($admin)->get('/admin/codes/qr-code?code=EQUITA2026&src=affiche&download=1')
            ->assertHeader('Content-Disposition', 'attachment; filename="qr-jackcie-equita2026-affiche.svg"');
        $this->asAdmin($admin)->get('/admin/codes/qr-code')->assertOk();
        $this->asAdmin($admin)->get('/admin/codes/qr-code?src=instagram')->assertOk();
        $this->asAdmin($admin)->get('/admin/codes/qr-code?code=INCONNU')->assertSessionHasErrors('code');
        $this->asAdmin($admin)->get('/admin/codes/qr-code?src=inconnue')->assertSessionHasErrors('src');
        $this->actingAs($this->user())->get('/admin/codes/qr-code')->assertNotFound();
    }
}
