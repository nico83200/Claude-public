<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Événements récurrents : dates retirées de la série (occurrence supprimée ou validée à part)
 * et lien d'une occurrence validée vers sa série d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->json('recurrence_exceptions')->nullable()->after('recurrence_until');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_exceptions')->constrained('calendar_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn('recurrence_exceptions');
        });
    }
};
