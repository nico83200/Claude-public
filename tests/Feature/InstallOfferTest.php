<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/** Après l'inscription (navigateur d'un téléphone ou d'une tablette) : proposition d'installer l'application, une fois. */
class InstallOfferTest extends TestCase
{
    public function test_install_is_offered_after_signup_until_dismissed(): void
    {
        $this->post('/register', ['name' => 'Lou Cavalière', 'email' => 'lou@exemple.fr', 'password' => 'Cheval2026secret', 'password_confirmation' => 'Cheval2026secret', 'terms' => '1'])
            ->assertRedirect()->assertSessionHas('offer_install', true);
        $user = User::where('email', 'lou@exemple.fr')->firstOrFail();

        // Page de confirmation de l'adresse e-mail, puis application : la proposition est présente.
        $this->get('/email/verify')->assertOk()->assertSee('data-install-offer', false)->assertSee('Installez Jack&amp;Cie sur votre', false);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->assertSee('data-install-offer', false);

        // « Plus tard » : elle ne revient plus.
        $this->postJson('/installation/plus-tard')->assertOk();
        $this->get('/dashboard')->assertOk()->assertDontSee('data-install-offer', false);
    }

    public function test_existing_users_are_not_shown_the_offer(): void
    {
        $this->postJson('/installation/plus-tard')->assertUnauthorized();
        $this->actingAs($this->user())->get('/dashboard')->assertOk()->assertDontSee('data-install-offer', false);
    }
}
