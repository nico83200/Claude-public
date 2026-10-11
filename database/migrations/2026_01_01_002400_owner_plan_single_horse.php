<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'offre « Particulier » devient « Propriétaire » : un seul cheval, toutes les fonctionnalités.
 * Plusieurs chevaux : offre « Propriétaire multi-chevaux ».
 * Appliqué seulement si l'offre n'a pas déjà été modifiée dans l'administration ; les abonnements
 * en cours conservent leurs 3 chevaux (dérogation sur la licence) jusqu'à leur fin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plan = DB::table('subscription_plans')->where('slug', 'particulier')->where('name', 'Particulier')->where('max_horses', 3)->first();
        if (! $plan) {
            return;
        }
        DB::table('subscription_plans')->where('id', $plan->id)->update([
            'name' => 'Propriétaire',
            'description' => 'Un cheval, toutes les fonctionnalités : assistant vocal, budget, documents, exports et partage.',
            'max_horses' => 1,
            'updated_at' => now(),
        ]);
        DB::table('subscription_plans')->where('slug', 'multi-chevaux')->where('description', 'Jusqu\'à 10 chevaux et partages avancés.')
            ->update(['description' => 'Plusieurs chevaux (jusqu\'à 10) et partages avancés.', 'updated_at' => now()]);

        DB::table('licenses')->where('subscription_plan_id', $plan->id)->whereIn('status', ['active', 'grace', 'cancel_scheduled', 'pending'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('id')->get(['id', 'limit_overrides'])
            ->each(function ($license) {
                $overrides = json_decode((string) $license->limit_overrides, true) ?: [];
                if (! array_key_exists('max_horses', $overrides)) {
                    $overrides['max_horses'] = 3;
                    DB::table('licenses')->where('id', $license->id)->update(['limit_overrides' => json_encode($overrides)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('subscription_plans')->where('slug', 'particulier')->where('name', 'Propriétaire')->where('max_horses', 1)
            ->update(['name' => 'Particulier', 'description' => 'Jusqu\'à 3 chevaux, partage et exports.', 'max_horses' => 3, 'updated_at' => now()]);
    }
};
