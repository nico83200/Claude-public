<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Accueil de l'application et déconnexion : renvoi vers le site vitrine, sauf dans l'application installée. */
class SiteRedirectTest extends TestCase
{
    public function test_guest_home_goes_to_showcase_site_but_login_and_signup_stay_in_app(): void
    {
        config(['equine.site_url' => 'https://jackcie.fr']);
        $this->get('/')->assertRedirect('https://jackcie.fr');
        $this->get('/login')->assertOk();
        $this->get('/register?code=EQUITA2026&src=site')->assertOk();
        // Application installée : jamais renvoyée vers le site.
        $this->get('/?source=pwa')->assertOk();
        $this->get('/dashboard?source=pwa')->assertRedirect('/login');
        // Connecté : tableau de bord.
        $this->actingAs($this->user())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_logout_goes_to_site_in_browser_and_to_login_in_installed_app(): void
    {
        config(['equine.site_url' => 'https://jackcie.fr']);
        $this->actingAs($this->user())->post('/logout')->assertRedirect('https://jackcie.fr');
        $this->assertGuest();
        $this->actingAs($this->user())->post('/logout', ['pwa' => '1'])->assertRedirect('/login');
        $this->assertGuest();
        $html = $this->actingAs($this->user())->get('/dashboard')->getContent();
        $this->assertStringContainsString('name="pwa" data-standalone', $html);
    }

    public function test_without_showcase_site_the_built_in_home_is_kept(): void
    {
        config(['equine.site_url' => null]);
        $this->get('/')->assertOk();
        $this->actingAs($this->user())->post('/logout')->assertRedirect('/login');
    }
}
