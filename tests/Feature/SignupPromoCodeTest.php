<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Entitlements;
use Tests\TestCase;

/** Code d'accès gratuit saisi à l'inscription (affiche de salon, lien ou QR code avec ?code=). */
class SignupPromoCodeTest extends TestCase
{
    private function equitaCode(array $attrs = []): PromoCode
    {
        return PromoCode::create($attrs + [
            'code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon EquitaLyon 2026',
            'subscription_plan_id' => SubscriptionPlan::where('slug', 'multi-chevaux')->value('id'), 'free_days' => 30, 'is_active' => true,
        ]);
    }

    private function register(array $extra = [])
    {
        return $this->post('/register', $extra + [
            'name' => 'Lou Cavalière', 'email' => 'lou@exemple.fr', 'password' => 'Cheval2026secret', 'password_confirmation' => 'Cheval2026secret', 'terms' => '1',
        ]);
    }

    public function test_link_prefills_code_and_registration_grants_30_days_of_every_feature_without_card(): void
    {
        $promo = $this->equitaCode();
        $this->get('/register?code=equita2026')->assertOk()->assertSessionHas('signup_code', 'EQUITA2026')
            ->assertSee('data-signup-banner', false)->assertSee('value="EQUITA2026"', false);

        $this->register(['promo_code' => 'EQUITA2026'])->assertRedirect()->assertSessionHas('success');

        $user = User::where('email', 'lou@exemple.fr')->firstOrFail();
        $org = $user->personalOrganization();
        $license = License::where('organization_id', $org->id)->where('source', 'code')->firstOrFail();
        $this->assertSame('multi-chevaux', $license->plan->slug);
        $this->assertSame(30, (int) round($license->starts_at->diffInDays($license->ends_at)));
        $this->assertNull($license->stripe_subscription_id ?? null);
        $this->assertSame(1, $promo->fresh()->redemptions_count);
        foreach (['budget', 'exports', 'documents', 'assistant', 'sharing'] as $feature) {
            $this->assertTrue(app(Entitlements::class)->hasFeature($org, $feature), $feature);
        }
        $this->assertNull(session('signup_code'));

        // Au bout des 30 jours, sans souscription : retour automatique à l'offre Découverte gratuite.
        $this->travel(31)->days();
        app(Entitlements::class)->flush();
        $ent = app(Entitlements::class)->for($org);
        $this->assertSame('decouverte', $ent['plan']->slug);
        $this->assertTrue($ent['writable']);
    }

    public function test_invalid_code_cancels_registration_with_a_clear_message(): void
    {
        $this->equitaCode(['expires_at' => now()->subDay()]);
        $this->from('/register')->register(['promo_code' => 'EQUITA2026'])->assertRedirect('/register')->assertSessionHasErrors(['promo_code' => 'Ce code a expiré.']);
        $this->from('/register')->register(['promo_code' => 'INCONNU'])->assertSessionHasErrors('promo_code');
        $this->assertDatabaseMissing('users', ['email' => 'lou@exemple.fr']);

        // Sans code, l'inscription reste possible normalement.
        $this->register()->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'lou@exemple.fr']);
        $this->assertSame(0, License::where('source', 'code')->count());
    }

    public function test_code_cannot_be_used_twice_on_the_same_space_and_respects_its_limit(): void
    {
        $this->equitaCode(['max_redemptions' => 1]);
        $this->register(['promo_code' => 'equita2026'])->assertRedirect();
        auth()->logout();
        $this->from('/register')->register(['email' => 'autre@exemple.fr', 'promo_code' => 'EQUITA2026'])
            ->assertSessionHasErrors(['promo_code' => 'Ce code a atteint son nombre maximal d\'utilisations.']);
    }
}
