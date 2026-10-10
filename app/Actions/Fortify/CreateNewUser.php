<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\OrganizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
            $this->organizations->createPersonal($user);

            return $user;
        });
    }
}
