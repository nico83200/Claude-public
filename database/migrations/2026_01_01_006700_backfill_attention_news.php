<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Reprise complétée (2.52.2) : la première reprise n'avait recopié que le jour d'ouverture des observations ; les nouvelles
 * et les clôtures passées rejoignent maintenant le suivi de leur propre jour. Sans doublon avec ce qui est déjà noté.
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
