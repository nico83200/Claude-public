<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Synchronisation de l'agenda (Google Agenda, Calendrier Apple) : adresse secrète par utilisateur, réservée aux offres payantes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 64)->nullable()->unique();
        });
        // Fonction ajoutée aux offres payantes existantes.
        foreach (DB::table('subscription_plans')->where('price_monthly', '>', 0)->get(['id', 'features']) as $plan) {
            $features = json_decode((string) $plan->features, true) ?: [];
            if (! in_array('calendar_sync', $features, true)) {
                $features[] = 'calendar_sync';
                DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode(array_values($features))]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['calendar_token']);
            $table->dropColumn('calendar_token');
        });
    }
};
