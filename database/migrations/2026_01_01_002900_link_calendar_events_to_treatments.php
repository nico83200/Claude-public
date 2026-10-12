<?php

use App\Services\CareCalendarSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un traitement a son début dans l'agenda ; un traitement saisi dans l'agenda est relié à sa fiche Santé. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreignId('treatment_id')->nullable()->after('care_record_id')->constrained('treatments')->nullOnDelete();
        });
        app(CareCalendarSync::class)->backfillTreatments();
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_id');
        });
    }
};
