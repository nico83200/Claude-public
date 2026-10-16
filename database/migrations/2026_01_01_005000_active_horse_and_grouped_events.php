<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plusieurs chevaux : cheval actif mémorisé par personne (ou « Tous les chevaux »),
 * et rendez-vous groupés (un passage du maréchal pour plusieurs chevaux).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('active_horse_id')->nullable()->after('user_id')->constrained('horses')->nullOnDelete();
            $table->boolean('all_horses')->default(false)->after('active_horse_id');
        });
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->uuid('group_uuid')->nullable()->index()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', fn (Blueprint $table) => $table->dropColumn('group_uuid'));
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_horse_id');
            $table->dropColumn('all_horses');
        });
    }
};
