<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\Audit;
use App\Services\OrganizationService;
use App\Support\Installation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Création / promotion d'un super-administrateur. Procédure réservée à
 * l'accès serveur : aucun formulaire public ne permet cette opération.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email} {--name=} {--promote : Promouvoir un compte existant}';

    protected $description = 'Crée (ou promeut) un compte super-administrateur';

    public function handle(OrganizationService $organizations): int
    {
        $email = strtolower($this->argument('email'));
        $existing = User::where('email', $email)->first();

        if ($existing) {
            if (! $this->option('promote')) {
                $this->error('Un compte existe déjà avec cet email. Utilisez --promote pour le promouvoir.');

                return self::FAILURE;
            }
            $existing->forceFill(['is_super_admin' => true])->save();
            Audit::log('admin.super_admin_promoted', $existing, ['via' => 'cli']);
            $this->info("{$email} est désormais super-administrateur. Il devra activer la double authentification pour accéder à /admin.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Nom affiché');
        $password = $this->secret('Mot de passe (12 caractères minimum)');
        $confirm = $this->secret('Confirmez le mot de passe');

        $validator = Validator::make(['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirm], [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'terms_accepted_at' => now()]);
        $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
        UserProfile::create(['user_id' => $user->id]);
        $organizations->createPersonal($user);
        Audit::log('admin.super_admin_created', $user, ['via' => 'cli']);

        Installation::markInstalled(['by' => 'cli']);
        $this->info("Super-administrateur {$email} créé. Connectez-vous puis activez la double authentification (Paramètres > Sécurité) : elle est obligatoire pour l'administration.");

        return self::SUCCESS;
    }
}
