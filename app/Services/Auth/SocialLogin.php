<?php

namespace App\Services\Auth;

use App\Models\FeatureFlag;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use App\Services\AcquisitionTracker;
use App\Services\Audit;
use App\Services\OrganizationService;
use App\Services\PromoCodeService;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Rattache une identité Google / Apple vérifiée à un compte Jack&Cie.
 *
 * - identité déjà liée : connexion au compte lié ;
 * - email confirmé par le fournisseur et compte existant : liaison automatique (email d'information) ;
 * - sinon : création du compte et de l'espace personnel (si les inscriptions sont ouvertes).
 * Un email non confirmé par le fournisseur n'est jamais utilisé pour rejoindre un compte existant.
 */
class SocialLogin
{
    public function __construct(private OrganizationService $organizations) {}

    /**
     * @param  array{sub: string, email: ?string, email_verified: bool, name: ?string}  $identity
     * @return array{user: User, created: bool, linked: bool}
     */
    public function login(string $provider, array $identity): array
    {
        $account = SocialAccount::with('user')->where('provider', $provider)->where('provider_user_id', $identity['sub'])->first();
        if ($account) {
            $this->ensureActive($account->user);
            $account->update(['email' => $identity['email'] ?? $account->email, 'last_used_at' => now()]);

            return ['user' => $account->user, 'created' => false, 'linked' => false];
        }

        if (! $identity['email']) {
            throw new RuntimeException(SocialAccount::PROVIDERS[$provider].' n\'a pas transmis d\'adresse email : connectez-vous avec votre mot de passe.');
        }
        $existing = User::where('email', $identity['email'])->first();
        if ($existing) {
            if (! $identity['email_verified']) {
                throw new RuntimeException('Un compte existe déjà avec '.$identity['email'].'. Connectez-vous avec votre mot de passe, puis associez '.SocialAccount::PROVIDERS[$provider].' dans Paramètres › Sécurité.');
            }
            $this->ensureActive($existing);
            $this->attach($existing, $provider, $identity);
            $existing->notify(new AppNotification('account_security', SocialAccount::PROVIDERS[$provider].' associé à votre compte',
                'La connexion avec '.SocialAccount::PROVIDERS[$provider].' ('.$identity['email'].') a été associée à votre compte. Si ce n\'est pas vous, changez votre mot de passe et retirez cette association dans Paramètres › Sécurité.',
                route('settings.security')));

            return ['user' => $existing, 'created' => false, 'linked' => true];
        }

        if (! FeatureFlag::enabled('registration')) {
            throw new RuntimeException('Les inscriptions sont temporairement fermées.');
        }

        $user = DB::transaction(function () use ($provider, $identity) {
            $user = User::create([
                'name' => mb_substr($identity['name'] ?: Str::of(Str::before($identity['email'], '@'))->replace(['.', '_', '-'], ' ')->title()->toString(), 0, 255),
                'email' => $identity['email'],
                // Mot de passe aléatoire inutilisable : la personne pourra en définir un par « Mot de passe oublié ».
                'password' => Str::password(40),
                'has_password' => false,
                // En continuant avec Google / Apple, la personne accepte les conditions affichées sous les boutons.
                'terms_accepted_at' => now(),
            ]);
            if ($identity['email_verified']) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            UserProfile::create(['user_id' => $user->id]);
            $this->organizations->createPersonal($user);
            $this->attach($user, $provider, $identity);

            return $user;
        });
        Audit::log('auth.social_registered', $user, ['provider' => $provider]);
        event(new Registered($user));
        app(AcquisitionTracker::class)->registered($user, $this->redeemSignupCode($user));
        // Inscription par le lien d'un parrain (jours offerts au parrain dès l'adresse e-mail confirmée).
        app(ReferralService::class)->attach($user, session('referral_code') ?? request()->cookie(ReferralService::COOKIE));

        return ['user' => $user->fresh(), 'created' => true, 'linked' => true];
    }

    /** Code d'accès gratuit venu du lien d'inscription (?code=) : activé sur l'espace personnel créé. */
    private function redeemSignupCode(User $user): bool
    {
        $code = session()->pull('signup_code');
        $org = $user->personalOrganization();
        if (! $code || ! $org) {
            return false;
        }
        try {
            $license = app(PromoCodeService::class)->redeemFreeAccess($code, $org, $user);
            session()->flash('promo_success', 'Votre code '.$code.' est activé : '.$license->plan->name.' offert jusqu\'au '.$license->ends_at->format('d/m/Y').', sans carte bancaire.');

            return true;
        } catch (ValidationException $e) {
            session()->flash('warning', 'Le code '.$code.' n\'a pas pu être activé : '.collect($e->errors())->flatten()->first().' Vous pouvez le saisir dans Abonnement.');

            return false;
        }
    }

    /** Associe l'identité au compte connecté (Paramètres › Sécurité). */
    public function link(User $user, string $provider, array $identity): void
    {
        $other = SocialAccount::where('provider', $provider)->where('provider_user_id', $identity['sub'])->first();
        if ($other && $other->user_id !== $user->id) {
            throw new RuntimeException('Ce compte '.SocialAccount::PROVIDERS[$provider].' est déjà associé à un autre compte Jack&Cie.');
        }
        if (! $other && $user->socialAccounts()->where('provider', $provider)->exists()) {
            throw new RuntimeException('Un autre compte '.SocialAccount::PROVIDERS[$provider].' est déjà associé : retirez-le d\'abord.');
        }
        if (! $other) {
            $this->attach($user, $provider, $identity);
        }
    }

    /** Ré-authentification (actions sensibles) : l'identité doit être celle liée au compte connecté. */
    public function confirms(User $user, string $provider, array $identity): bool
    {
        return $user->socialAccounts()->where('provider', $provider)->where('provider_user_id', $identity['sub'])->exists();
    }

    /** Retire une association, sauf si c'est le dernier moyen de connexion du compte. */
    public function unlink(User $user, string $provider): void
    {
        if (! $user->has_password && $user->socialAccounts()->where('provider', '!=', $provider)->doesntExist()) {
            throw new RuntimeException('C\'est votre seul moyen de connexion : définissez d\'abord un mot de passe.');
        }
        $user->socialAccounts()->where('provider', $provider)->delete();
        Audit::log('auth.social_unlinked', $user, ['provider' => $provider]);
    }

    private function attach(User $user, string $provider, array $identity): void
    {
        $user->socialAccounts()->create(['provider' => $provider, 'provider_user_id' => $identity['sub'], 'email' => $identity['email'], 'last_used_at' => now()]);
        Audit::log('auth.social_linked', $user, ['provider' => $provider]);
    }

    private function ensureActive(User $user): void
    {
        if ($user->isSuspended()) {
            throw new RuntimeException('Ce compte est suspendu. Contactez le support.');
        }
    }
}
