<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Offres initiales — simples points de départ, entièrement modifiables depuis
 * l'administration. Les identifiants de prix Stripe doivent être renseignés
 * dans l'administration (ou via `php artisan billing:sync-stripe-prices`).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['slug' => 'decouverte', 'name' => 'Découverte', 'description' => 'Gratuit, pour suivre un cheval.', 'price_monthly' => null, 'price_yearly' => null, 'audience' => 'individual', 'is_default_free' => true, 'max_horses' => 1, 'max_members' => 1, 'max_invitations' => 1, 'storage_mb' => 100, 'features' => ['offline', 'sharing'], 'sort_order' => 0],
            ['slug' => 'particulier', 'name' => 'Particulier', 'description' => 'Jusqu\'à 3 chevaux, partage et exports.', 'price_monthly' => 6.90, 'price_yearly' => 69.00, 'audience' => 'individual', 'max_horses' => 3, 'max_members' => 2, 'max_invitations' => 6, 'storage_mb' => 2048, 'features' => ['offline', 'sharing', 'horse_search', 'budget', 'exports', 'documents'], 'trial_days' => 14, 'sort_order' => 1],
            ['slug' => 'multi-chevaux', 'name' => 'Propriétaire multi-chevaux', 'description' => 'Jusqu\'à 10 chevaux et partages avancés.', 'price_monthly' => 14.90, 'price_yearly' => 149.00, 'audience' => 'individual', 'max_horses' => 10, 'max_members' => 5, 'max_invitations' => 30, 'storage_mb' => 10240, 'features' => ['offline', 'sharing', 'horse_search', 'budget', 'exports', 'documents'], 'trial_days' => 14, 'sort_order' => 2],
            ['slug' => 'ecurie', 'name' => 'Écurie', 'description' => 'Gestion collective : membres, rôles, chevaux en pension.', 'price_monthly' => 49.00, 'price_yearly' => 490.00, 'audience' => 'stable', 'max_horses' => 80, 'max_members' => 30, 'max_invitations' => 200, 'storage_mb' => 51200, 'features' => ['offline', 'sharing', 'stable', 'horse_search', 'budget', 'exports', 'documents'], 'trial_days' => 14, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
