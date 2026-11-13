<?php

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Database\Migrations\Migration;

/**
 * 2.53 : les partages de chevaux s'ouvrent d'office. Ceux encore en attente pour un compte à l'adresse vérifiée
 * sont acceptés pendant la mise à jour (la personne est prévenue) ; les demi-pensions restent à accepter.
 */
return new class extends Migration
{
    public function up(): void
    {
        $emails = Invitation::pending()->where('type', 'horse_access')->pluck('email')->unique();
        foreach (User::whereIn('email', $emails)->whereNotNull('email_verified_at')->get() as $user) {
            app(InvitationService::class)->autoAcceptShares($user);
        }
    }

    public function down(): void
    {
        // Rien à défaire : les accès ouverts restent (chaque personne peut quitter un partage).
    }
};
