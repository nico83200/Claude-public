<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Audit;
use App\Services\Auth\OidcProvider;
use App\Services\Auth\SocialLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * « Continuer avec Google / Apple » : inscription, connexion, association au compte
 * et confirmation d'identité avant une action sensible.
 *
 * Le contexte de la demande (state, nonce, vérificateur PKCE) est gardé dans un cookie chiffré
 * propre à ce navigateur : Apple renvoie la réponse par un POST venant de son domaine, qui
 * n'emporte pas le cookie de session. Le retour vérifie l'identité, la met de côté une minute
 * puis redirige vers une page du site, où la connexion se fait dans la session habituelle.
 */
class SocialAuthController extends Controller
{
    private const COOKIE = 'jc_oauth';

    private const INTENTS = ['login', 'link', 'confirm'];

    public function redirect(Request $request, string $provider)
    {
        abort_unless(OidcProvider::configured($provider), 404);
        $intent = in_array($request->query('intent'), self::INTENTS, true) ? $request->query('intent') : 'login';
        if ($intent !== 'login' && ! $request->user()) {
            return redirect()->route('login');
        }
        if ($intent === 'login' && $request->user()) {
            return redirect()->route('dashboard');
        }
        $context = [
            'provider' => $provider,
            'intent' => $intent,
            'state' => Str::random(40),
            'nonce' => Str::random(40),
            'verifier' => Str::random(64),
            'remember' => $request->boolean('remember', true),
        ];
        $url = (new OidcProvider($provider))->authorizationUrl(route('social.callback', $provider), $context['state'], $context['nonce'], $context['verifier']);

        return redirect()->away($url)->withCookie($this->contextCookie(json_encode($context), 10, $request));
    }

    /** Retour du fournisseur (GET pour Google, POST pour Apple). */
    public function callback(Request $request, string $provider)
    {
        abort_unless(OidcProvider::configured($provider), 404);
        $context = json_decode((string) $request->cookie(self::COOKIE), true);
        if (! is_array($context) || ($context['provider'] ?? null) !== $provider || ! hash_equals((string) $context['state'], (string) $request->input('state'))) {
            return $this->fail('La demande de connexion a expiré ou ne provient pas de ce navigateur. Réessayez.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            // Annulation par la personne (« user_cancelled_authorize », « access_denied »…).
            return $this->fail(null, $context['intent']);
        }

        try {
            $identity = (new OidcProvider($provider))->identity((string) $request->input('code'), route('social.callback', $provider), $context['nonce'], $context['verifier']);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Connexion avec '.SocialAccount::PROVIDERS[$provider].' impossible. Réessayez ou utilisez votre email et votre mot de passe.', $context['intent']);
        }
        // Apple ne transmet le nom qu'à la première autorisation, hors du jeton d'identité.
        if ($provider === 'apple' && ! $identity['name'] && is_string($request->input('user'))) {
            $name = json_decode($request->input('user'), true)['name'] ?? [];
            $identity['name'] = trim(($name['firstName'] ?? '').' '.($name['lastName'] ?? '')) ?: null;
        }

        $ticket = Str::random(48);
        Cache::put('social-ticket:'.$ticket, ['identity' => $identity, 'context' => $context], now()->addMinute());

        return redirect()->route('social.finish', ['provider' => $provider, 'ticket' => $ticket], 303);
    }

    /** Suite du retour, dans la session habituelle du site (le cookie de contexte est effacé). */
    public function finish(Request $request, string $provider, SocialLogin $social)
    {
        return $this->complete($request, $provider, $social)->withCookie(Cookie::forget(self::COOKIE));
    }

    private function complete(Request $request, string $provider, SocialLogin $social)
    {
        $data = Cache::pull('social-ticket:'.$request->query('ticket'));
        $context = json_decode((string) $request->cookie(self::COOKIE), true);
        // Le ticket n'est utilisable qu'une fois, et seulement par le navigateur qui a lancé la demande.
        if (! $data || ! is_array($context) || ! hash_equals((string) $data['context']['state'], (string) ($context['state'] ?? '')) || $data['context']['provider'] !== $provider) {
            return $this->fail('La demande de connexion a expiré. Réessayez.');
        }
        ['identity' => $identity, 'context' => $context] = $data;
        $label = SocialAccount::PROVIDERS[$provider];

        try {
            if ($context['intent'] === 'link') {
                abort_unless($request->user(), 403);
                $social->link($request->user(), $provider, $identity);

                return redirect()->route('settings.security')->with('success', $label.' est associé à votre compte : vous pouvez l\'utiliser pour vous connecter.');
            }
            if ($context['intent'] === 'confirm') {
                abort_unless($request->user(), 403);
                if (! $social->confirms($request->user(), $provider, $identity)) {
                    throw new RuntimeException('Ce compte '.$label.' n\'est pas celui associé à votre compte Jack&Cie.');
                }
                $request->session()->put('auth.password_confirmed_at', time());

                return redirect()->intended(route('dashboard'));
            }

            $result = $social->login($provider, $identity);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), $context['intent']);
        }

        $user = $result['user'];
        // Double authentification activée : le code reste demandé, comme avec un mot de passe.
        if ($user->hasConfirmedTwoFactor()) {
            $request->session()->put(['login.id' => $user->id, 'login.remember' => (bool) $context['remember']]);

            return redirect()->route('two-factor.login');
        }
        Auth::login($user, (bool) $context['remember']);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::log('auth.social_login', $user, ['provider' => $provider, 'created' => $result['created']]);

        return $result['created']
            ? redirect()->route('dashboard')->with('success', trim('Bienvenue ! Votre compte est créé avec '.$label.'. '.$request->session()->pull('promo_success')))
            : redirect()->intended(route('dashboard'));
    }

    public function unlink(Request $request, string $provider, SocialLogin $social)
    {
        abort_unless(isset(SocialAccount::PROVIDERS[$provider]), 404);
        try {
            $social->unlink($request->user(), $provider);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', SocialAccount::PROVIDERS[$provider].' n\'est plus associé à votre compte.');
    }

    private function fail(?string $message, string $intent = 'login')
    {
        $route = match ($intent) {
            'link' => 'settings.security',
            'confirm' => 'password.confirm',
            default => 'login',
        };
        $redirect = redirect()->route($route)->withCookie(Cookie::forget(self::COOKIE));

        return $message ? $redirect->with('error', $message) : $redirect->with('status', 'Connexion annulée.');
    }

    /** Cookie du contexte : SameSite=None (sur HTTPS) pour revenir avec le POST d'Apple. */
    private function contextCookie(string $value, int $minutes, Request $request)
    {
        $secure = $request->isSecure();

        return cookie(self::COOKIE, $value, $minutes, '/', null, $secure, true, false, $secure ? 'none' : 'lax');
    }
}
