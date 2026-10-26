<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Reprise faite une fois pendant la mise à jour (aussi depuis l'administration, sans SSH) : les observations passées
 * complètent le suivi du jour où elles ont été signalées. Même travail que `php artisan attention:backfill-daily-logs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('attention:backfill-daily-logs');
    }

    public function down(): void
    {
        // Rien à défaire : les lignes ajoutées font partie du suivi du jour.
    }
};
