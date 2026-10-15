<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\AcquisitionTracker;
use App\Services\OrganizationService;
use App\Services\PromoCodeService;
use App\Services\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private OrganizationService $organizations) {}

    /**
     * Inscription : crée le compte et l'espace personnel associé.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ], [
            'terms.accepted' => 'Vous devez accepter les conditions d\'utilisation et la politique de confidentialité.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => strtolower($input['email']),
                'password' => $input['password'],
                'terms_accepted_at' => now(),
            ]);
            UserProfile::create(['user_id' => $user->id]);
            $org = $this->organizations->createPersonal($user);
            // Code d'accès gratuit saisi à l'inscription (salon, partenaire) : un code refusé annule l'inscription
            // pour que la personne puisse le corriger, plutôt que de créer un compte sans l'avantage promis.
            $redeemed = false;
            if (filled($input['promo_code'] ?? null)) {
                try {
                    $license = app(PromoCodeService::class)->redeemFreeAccess($input['promo_code'], $org, $user);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(['promo_code' => collect($e->errors())->flatten()->first()]);
                }
                session()->forget('signup_code');
                $redeemed = true;
                session()->flash('success', 'Bienvenue ! Votre code est activé : '.$license->plan->name.' offert jusqu\'au '.$license->ends_at->format('d/m/Y').', sans carte bancaire.');
            }
            app(AcquisitionTracker::class)->registered($user, $redeemed);
            // Inscription par le lien d'un parrain (jours offerts au parrain dès l'adresse e-mail confirmée).
            app(ReferralService::class)->attach($user, session('referral_code') ?? request()->cookie(ReferralService::COOKIE));

            return $user;
        });
    }
}
