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
            ['slug' => 'decouverte', 'name' => 'Découverte', 'description' => 'Gratuit, pour suivre un cheval.', 'price_monthly' => null, 'price_yearly' => null, 'audience' => 'individual', 'is_default_free' => true, 'max_horses' => 1, 'max_members' => 1, 'max_invitations' => 1, 'storage_mb' => 100, 'features' => ['sharing'], 'sort_order' => 0],
            ['slug' => 'particulier', 'name' => 'Propriétaire', 'description' => 'Un cheval, toutes les fonctionnalités : assistant vocal, budget, documents, exports et partage.', 'price_monthly' => 6.90, 'price_yearly' => 69.00, 'audience' => 'individual', 'max_horses' => 1, 'max_members' => 2, 'max_invitations' => 6, 'storage_mb' => 2048, 'assistant_monthly' => 150, 'features' => ['sharing', 'budget', 'exports', 'documents', 'assistant', 'calendar_sync'], 'trial_days' => 14, 'sort_order' => 1],
            ['slug' => 'multi-chevaux', 'name' => 'Propriétaire multi-chevaux', 'description' => 'Plusieurs chevaux (jusqu\'à 10) et partages avancés.', 'price_monthly' => 14.90, 'price_yearly' => 149.00, 'audience' => 'individual', 'max_horses' => 10, 'max_members' => 5, 'max_invitations' => 30, 'storage_mb' => 10240, 'assistant_monthly' => 400, 'features' => ['sharing', 'budget', 'exports', 'documents', 'assistant', 'calendar_sync'], 'trial_days' => 14, 'sort_order' => 2],
            ['slug' => 'ecurie', 'name' => 'Écurie', 'description' => 'Gestion collective : membres, rôles, chevaux en pension.', 'price_monthly' => 49.00, 'price_yearly' => 490.00, 'audience' => 'stable', 'max_horses' => 80, 'max_members' => 30, 'max_invitations' => 200, 'storage_mb' => 51200, 'assistant_monthly' => 2000, 'features' => ['sharing', 'stable', 'budget', 'exports', 'documents', 'assistant', 'calendar_sync'], 'trial_days' => 14, 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
