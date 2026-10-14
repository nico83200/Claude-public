<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Déménagement vers app.jackcie.fr : l'ancienne adresse redirige, QR codes et liens imprimés compris. */
class LegacyHostRedirectTest extends TestCase
{
    public function test_old_domain_redirects_permanently_to_new_address_with_same_path(): void
    {
        config(['app.url' => 'https://app.jackcie.fr', 'app.legacy_hosts' => 'jackcie.nlapps.fr']);

        $this->get('http://jackcie.nlapps.fr/urgence/ABCtoken?x=1')->assertStatus(301)->assertRedirect('https://app.jackcie.fr/urgence/ABCtoken?x=1');
        $this->get('https://jackcie.nlapps.fr/register?code=EQUITA2026&src=affiche')->assertStatus(301)->assertRedirect('https://app.jackcie.fr/register?code=EQUITA2026&src=affiche');
        $this->post('https://jackcie.nlapps.fr/login', ['email' => 'x@y.fr'])->assertStatus(308)->assertRedirect('https://app.jackcie.fr/login');

        // Webhook Stripe : traité sur place (Stripe ne suit pas les redirections), refusé ici faute de signature.
        $this->call('POST', 'https://jackcie.nlapps.fr/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad', 'CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(400);

        // Nouvelle adresse : aucune redirection.
        $this->get('https://app.jackcie.fr/login')->assertOk();
    }

    public function test_no_redirect_while_app_url_is_still_the_old_domain(): void
    {
        config(['app.url' => 'https://jackcie.nlapps.fr', 'app.legacy_hosts' => 'jackcie.nlapps.fr']);
        $this->get('https://jackcie.nlapps.fr/login')->assertOk();
    }
}
