<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\FeatureFlag;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\OidcProvider;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Tests\TestCase;

/**
 * « Continuer avec Google / Apple » contre un faux fournisseur OpenID Connect :
 * vraies clés (RSA, EC) et vraies signatures, seules les réponses HTTP sont simulées.
 */
class SocialAuthTest extends TestCase
{
    private JWK $providerKey;

    private string $applePem = '';

    /** Revendications du prochain jeton d'identité renvoyé par le faux fournisseur. */
    private array $nextClaims = [];

    private ?JWK $signWith = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->providerKey = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key']);
        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ec, $this->applePem);
        config([
            'services.google' => ['client_id' => 'client-123.apps.googleusercontent.com', 'client_secret' => 'google-secret'],
            'services.apple' => ['client_id' => 'fr.nlapps.jackcie.web', 'team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'private_key' => $this->applePem],
        ]);
        Cache::flush();
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), 'certs') || str_ends_with($request->url(), '/auth/keys')) {
                return Http::response((new JWKSet([$this->providerKey->toPublic()]))->jsonSerialize());
            }
            if (str_contains($request->url(), 'token')) {
                return Http::response(['access_token' => 'at', 'id_token' => $this->idToken($this->nextClaims)]);
            }

            return Http::response([], 404);
        });
    }

    private function idToken(array $claims): string
    {
        $jws = (new JWSBuilder(new AlgorithmManager([new RS256])))->create()->withPayload(json_encode($claims))
            ->addSignature($this->signWith ?? $this->providerKey, ['alg' => 'RS256', 'kid' => 'test-key'])->build();

        return (new CompactSerializer)->serialize($jws, 0);
    }

    /**
     * Parcours complet : départ vers le fournisseur, retour, puis fin dans la session du site.
     * $claims complète les revendications par défaut ; $tamper modifie la réponse avant le retour.
     */
    private function signIn(string $provider, array $claims, string $query = '', ?callable $tamper = null, array $post = [])
    {
        $start = $this->get("/connexion/{$provider}{$query}");
        $start->assertRedirect();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $params);
        $cookie = $start->getCookie('jc_oauth')->getValue(); // déchiffré par le client de test

        $this->nextClaims = array_merge([
            'iss' => $provider === 'google' ? 'https://accounts.google.com' : 'https://appleid.apple.com',
            'aud' => config("services.{$provider}.client_id"),
            'sub' => 'sub-'.$provider.'-1', 'email' => 'camille@exemple.fr', 'email_verified' => true,
            'iat' => time(), 'exp' => time() + 600, 'nonce' => $params['nonce'],
        ], $claims);
        $state = $params['state'];
        if ($tamper) {
            [$state, $cookie] = $tamper($state, $cookie);
        }

        $callback = $provider === 'apple'
            ? $this->withCookie('jc_oauth', $cookie)->post('/connexion/apple/retour', ['code' => 'code-1', 'state' => $state] + $post)
            : $this->withCookie('jc_oauth', $cookie)->get('/connexion/google/retour?'.http_build_query(['code' => 'code-1', 'state' => $state]));
        if ($callback->status() !== 303) {
            return $callback;
        }

        return $this->withCookie('jc_oauth', $cookie)->get($callback->headers->get('Location'));
    }

    public function test_buttons_only_when_provider_configured(): void
    {
        $this->get('/login')->assertSee('data-social="google"', false)->assertSee('data-social="apple"', false)->assertSee('En continuant avec Google ou Apple');
        $this->get('/register')->assertSee('Continuer avec Google');

        config(['services.apple.private_key' => null, 'services.google.client_secret' => null]);
        $this->get('/login')->assertDontSee('data-social', false)->assertDontSee('En continuant avec Google');
        $this->get('/connexion/google')->assertNotFound();
    }

    public function test_authorization_request_uses_state_nonce_and_pkce(): void
    {
        $r = $this->get('/connexion/google');
        parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $p);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $r->headers->get('Location'));
        $this->assertSame('client-123.apps.googleusercontent.com', $p['client_id']);
        $this->assertSame(route('social.callback', 'google'), $p['redirect_uri']);
        $this->assertSame('S256', $p['code_challenge_method']);
        $this->assertSame(40, strlen($p['state']));
        $this->assertSame(40, strlen($p['nonce']));
        $this->assertStringNotContainsString('google-secret', $r->headers->get('Location'));

        $a = $this->get('/connexion/apple');
        parse_str(parse_url($a->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('form_post', $q['response_mode']);
        $this->assertSame('name email', $q['scope']);
    }

    public function test_google_creates_account_with_personal_space(): void
    {
        $this->signIn('google', ['name' => 'Camille Martin'])->assertRedirect('/dashboard')->assertSessionHas('success');

        $user = User::where('email', 'camille@exemple.fr')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Camille Martin', $user->name);
        $this->assertFalse($user->has_password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->personalOrganization());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'sub-google-1']);
        // Le code a été échangé avec le secret et le vérificateur PKCE, côté serveur uniquement.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && $r['client_secret'] === 'google-secret' && strlen($r['code_verifier']) === 64);

        // Connexion suivante : même compte, aucune création.
        auth()->logout();
        $this->signIn('google', ['email' => 'nouvelle-adresse@exemple.fr'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::where('email', 'like', '%exemple.fr')->count());
    }

    public function test_existing_account_is_linked_only_with_a_verified_email(): void
    {
        $user = $this->user(['email' => 'camille@exemple.fr']);

        $this->signIn('google', ['email_verified' => false])->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);

        $this->signIn('google', [])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google']);
        $this->assertTrue($user->fresh()->has_password);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    public function test_forged_or_mismatched_tokens_are_refused(): void
    {
        // Signé par une autre clé que celles publiées par le fournisseur.
        $this->signWith = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'kid' => 'test-key']);
        $this->signIn('google', [])->assertRedirect('/login')->assertSessionHas('error');
        $this->signWith = null;

        foreach ([['aud' => 'autre-client'], ['iss' => 'https://evil.example'], ['exp' => time() - 3600], ['nonce' => 'rejoue']] as $bad) {
            $this->signIn('google', $bad)->assertRedirect('/login')->assertSessionHas('error');
        }
        // État différent de celui du navigateur (CSRF de connexion).
        $this->signIn('google', [], '', fn ($state, $cookie) => ['autre-etat', $cookie])->assertRedirect('/login')->assertSessionHas('error');
        // Ticket de fin réutilisé ou volé sans le cookie du navigateur.
        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_finish_ticket_requires_the_initiating_browser_and_is_single_use(): void
    {
        $start = $this->get('/connexion/google');
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $p);
        $cookie = $start->getCookie('jc_oauth')->getValue();
        $this->nextClaims = ['iss' => 'https://accounts.google.com', 'aud' => 'client-123.apps.googleusercontent.com', 'sub' => 's', 'email' => 'x@exemple.fr', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 600, 'nonce' => $p['nonce']];
        $finish = $this->withCookie('jc_oauth', $cookie)->get('/connexion/google/retour?'.http_build_query(['code' => 'c', 'state' => $p['state']]))->headers->get('Location');

        $this->withUnencryptedCookies([])->call('GET', $finish)->assertRedirect('/login'); // sans le cookie : refusé…
        $this->withCookie('jc_oauth', $cookie)->get($finish)->assertRedirect('/login'); // …et le ticket est consommé
        $this->assertGuest();
    }

    public function test_apple_form_post_with_name_and_signed_client_secret(): void
    {
        $this->signIn('apple', ['email' => 'abc123@privaterelay.appleid.com', 'email_verified' => 'true'], '', null,
            ['user' => json_encode(['name' => ['firstName' => 'Léa', 'lastName' => 'Durand'], 'email' => 'abc123@privaterelay.appleid.com'])])
            ->assertRedirect('/dashboard');

        $user = User::where('email', 'abc123@privaterelay.appleid.com')->firstOrFail();
        $this->assertSame('Léa Durand', $user->name);
        $this->assertAuthenticatedAs($user);

        // Secret client : jeton ES256 signé avec la clé .p8, vérifiable avec sa clé publique.
        Http::assertSent(function (HttpRequest $r) {
            if (! str_contains($r->url(), 'appleid.apple.com/auth/token')) {
                return false;
            }
            $jws = (new CompactSerializer)->unserialize($r['client_secret']);
            $claims = json_decode($jws->getPayload(), true);
            $ok = (new JWSVerifier(new AlgorithmManager([new ES256])))->verifyWithKey($jws, JWKFactory::createFromKey($this->applePem)->toPublic(), 0);

            return $ok && $jws->getSignature(0)->getProtectedHeaderParameter('kid') === 'KEY1234567'
                && $claims['iss'] === 'TEAM123456' && $claims['sub'] === 'fr.nlapps.jackcie.web' && $claims['aud'] === 'https://appleid.apple.com' && $claims['exp'] - $claims['iat'] <= 600;
        });
    }

    public function test_two_factor_is_still_required(): void
    {
        $user = $this->user(['email' => 'camille@exemple.fr']);
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'sub-google-1']);

        $this->signIn('google', [])->assertRedirect(route('two-factor.login'))->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_refused_when_registration_closed_or_account_suspended(): void
    {
        FeatureFlag::updateOrCreate(['key' => 'registration'], ['enabled_globally' => false]);
        cache()->forget('flag:registration');
        $this->signIn('google', [])->assertRedirect('/login')->assertSessionHas('error', 'Les inscriptions sont temporairement fermées.');
        $this->assertDatabaseMissing('users', ['email' => 'camille@exemple.fr']);

        $user = $this->user(['email' => 'camille@exemple.fr']);
        $user->forceFill(['suspended_at' => now()])->save();
        $this->signIn('google', [])->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_link_unlink_and_confirm_from_settings(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/parametres/securite')->assertSee('Connexion avec Google ou Apple')->assertSee('?intent=link', false);

        // Association à un compte Google d'une autre adresse.
        $this->actingAs($user);
        $this->signIn('google', ['email' => 'perso@gmail.com'], '?intent=link')->assertRedirect('/parametres/securite')->assertSessionHas('success');
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'email' => 'perso@gmail.com']);

        // Déjà lié à un autre compte : refusé.
        $other = $this->user();
        $this->actingAs($other);
        $this->signIn('google', [], '?intent=link')->assertSessionHas('error');
        $this->assertSame(1, SocialAccount::count());

        // Confirmation d'identité avant une action sensible.
        $this->actingAs($user);
        $this->get('/user/confirm-password')->assertSee('Confirmer avec Google');
        $this->signIn('google', [], '?intent=confirm')->assertRedirect();
        $this->assertNotNull(session('auth.password_confirmed_at'));

        // Retrait : possible avec un mot de passe, refusé si c'est le seul moyen de connexion.
        $this->delete('/parametres/securite/google')->assertSessionHas('success');
        $social = User::factory()->create(['has_password' => false]);
        $social->socialAccounts()->create(['provider' => 'apple', 'provider_user_id' => 'x']);
        $this->actingAs($social)->delete('/parametres/securite/apple')->assertSessionHas('error');
        $this->assertDatabaseHas('social_accounts', ['user_id' => $social->id]);
    }

    public function test_signup_link_code_is_activated_after_google_registration(): void
    {
        \App\Models\PromoCode::create(['code' => 'EQUITA2026', 'type' => 'free_access', 'label' => 'Salon', 'is_active' => true,
            'subscription_plan_id' => \App\Models\SubscriptionPlan::where('slug', 'multi-chevaux')->value('id'), 'free_days' => 30]);
        $this->get('/register?code=EQUITA2026')->assertOk();
        $this->signIn('google', ['name' => 'Camille Martin'])->assertRedirect('/dashboard')
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'EQUITA2026') && str_contains($m, 'sans carte bancaire'));
        $org = User::where('email', 'camille@exemple.fr')->firstOrFail()->personalOrganization();
        $this->assertDatabaseHas('licenses', ['organization_id' => $org->id, 'source' => 'code']);
    }

    public function test_cancelled_by_user_returns_to_login_quietly(): void
    {
        $start = $this->get('/connexion/apple');
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $p);
        $cookie = $start->getCookie('jc_oauth')->getValue();
        $this->withCookie('jc_oauth', $cookie)->post('/connexion/apple/retour', ['error' => 'user_cancelled_authorize', 'state' => $p['state']])
            ->assertRedirect('/login')->assertSessionHas('status', 'Connexion annulée.');
    }

    public function test_admin_saves_credentials_encrypted_and_checks_apple_key(): void
    {
        config(['services.google' => ['client_id' => null, 'client_secret' => null], 'services.apple' => ['client_id' => null, 'team_id' => null, 'key_id' => null, 'private_key' => null]]);
        $admin = $this->user();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

        $this->put('/admin/integrations/connexion', ['google_client_id' => 'abc.apps.googleusercontent.com', 'google_client_secret' => 'GOCSPX-secret-1234',
            'apple_client_id' => 'fr.nlapps.jackcie.web', 'apple_team_id' => 'TEAM123456', 'apple_key_id' => 'KEY1234567', 'apple_private_key' => $this->applePem])
            ->assertSessionHas('success');
        $this->assertSame(['google', 'apple'], OidcProvider::available());
        $stored = ApplicationSetting::where('key', 'integration.google.client_secret')->value('value');
        $this->assertStringNotContainsString('GOCSPX', (string) $stored);
        $this->get('/admin/integrations')->assertSee('••••1234')->assertDontSee('GOCSPX-secret-1234')->assertDontSee('BEGIN PRIVATE KEY-----M', false)
            ->assertSee(route('social.callback', 'apple'));

        $this->put('/admin/integrations/connexion', ['apple_client_id' => 'fr.nlapps.jackcie.web', 'apple_team_id' => 'TEAM123456', 'apple_key_id' => 'KEY1234567',
            'apple_private_key' => "-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----", 'google_client_id' => 'abc.apps.googleusercontent.com'])
            ->assertSessionHas('error');
    }
}
