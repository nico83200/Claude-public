<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence uniquement. Les données de démonstration sont
     * isolées dans DemoSeeder (php artisan db:seed --class=DemoSeeder),
     * refusé en production.
     */
    public function run(): void
    {
        $this->call([ReferenceDataSeeder::class, PlanSeeder::class]);
    }
}
