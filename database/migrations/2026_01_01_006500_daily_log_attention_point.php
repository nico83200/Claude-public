<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Suivi du jour complété par un point d'attention (ouverture, nouvelle, clôture) : lien vers son dossier. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            $table->foreignId('health_observation_id')->nullable()->after('anomalies')->constrained(indexName: 'dl_observation_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropForeign('dl_observation_fk');
            $table->dropColumn('health_observation_id');
        });
    }
};
