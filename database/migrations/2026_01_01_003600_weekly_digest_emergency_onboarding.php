<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Récapitulatif hebdomadaire, démarrage guidé et fiche d'urgence du cheval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->boolean('weekly_digest')->default(true);
            $table->date('weekly_digest_sent_on')->nullable();
            $table->timestamp('app_installed_at')->nullable();
            $table->timestamp('onboarding_dismissed_at')->nullable();
        });
        Schema::table('horses', function (Blueprint $table) {
            // Lien public de la fiche d'urgence (QR code sur le box) : null = désactivée.
            $table->string('emergency_token', 48)->nullable()->unique();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->text('emergency_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn(['weekly_digest', 'weekly_digest_sent_on', 'app_installed_at', 'onboarding_dismissed_at']));
        Schema::table('horses', fn (Blueprint $table) => $table->dropColumn(['emergency_token', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_notes']));
    }
};
