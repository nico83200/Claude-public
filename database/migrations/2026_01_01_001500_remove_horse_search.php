<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2.6 : suppression de « Rechercher mon cheval » (recherche d'identité sur Internet).
 * La table horse_external_sources est conservée sans usage : les informations déjà importées ne sont pas effacées.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('feature_flags')->where('key', 'horse_search')->delete();
        foreach (DB::table('subscription_plans')->get(['id', 'features']) as $plan) {
            $features = json_decode((string) $plan->features, true);
            if (is_array($features) && in_array('horse_search', $features, true)) {
                DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode(array_values(array_diff($features, ['horse_search'])))]);
            }
        }
    }

    public function down(): void
    {
        // Fonction retirée : rien à restaurer.
    }
};
