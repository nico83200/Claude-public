<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suppression du mode hors ligne : la fonctionnalité disparaît des offres et des
 * interrupteurs. Les tables de synchronisation (sync_*, offline_horse_selections)
 * sont volontairement conservées, sans être utilisées.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('subscription_plans')->get(['id', 'features']) as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            if (in_array('offline', $features, true)) {
                DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode(array_values(array_diff($features, ['offline'])))]);
            }
        }
        DB::table('feature_flags')->where('key', 'offline')->delete();
    }

    public function down(): void
    {
        // Rien à restaurer automatiquement : réactiver la fonctionnalité demanderait l'ancien code.
    }
};
