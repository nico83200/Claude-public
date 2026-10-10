<?php

namespace App\Services;

use App\Models\Horse;
use App\Models\HorseAccessGrant;
use App\Models\OrganizationMember;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Suppression de compte (droit à l'effacement) : anonymisation du compte,
 * fermeture des accès et suppression logique des données de l'espace
 * personnel. Les données d'écuries tierces (soins saisis pour leurs chevaux,
 * factures) sont conservées sans identification nominative de l'auteur.
 * La purge définitive intervient via `php artisan data:purge` après le délai
 * de conservation.
 */
class AccountDeletionService
{
    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            $personal = $user->personalOrganization();
            if ($personal) {
                Horse::where('organization_id', $personal->id)->get()->each->delete();
                $personal->delete();
            }
            HorseAccessGrant::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            OrganizationMember::where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('offline_horse_selections')->where('user_id', $user->id)->delete();
            SyncDevice::where('user_id', $user->id)->update(['wipe_requested_at' => now()]);
            $user->profile()?->delete();
            $user->notifications()->delete();
            $user->forceFill([
                'name' => 'Utilisateur supprimé',
                'email' => 'supprime-'.$user->id.'-'.Str::lower(Str::random(6)).'@invalid.local',
                'password' => Str::random(64),
                'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
                'remember_token' => null, 'current_organization_id' => null, 'suspended_at' => now(),
            ])->save();
            $user->delete();
        });
        Audit::log('gdpr.account_anonymized', $user);
    }
}
