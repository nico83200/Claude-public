<?php

use App\Services\CareCalendarSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda ↔ Santé : un soin réalisé a son événement « réalisé » dans l'agenda, et un rendez-vous de soin
 * réalisé (maréchal, vétérinaire, soin) a son soin dans le carnet de santé. Reprise des données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreignId('care_record_id')->nullable()->after('source_id')->constrained('care_records')->nullOnDelete();
        });
        app(CareCalendarSync::class)->backfill();
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('care_record_id');
        });
    }
};
