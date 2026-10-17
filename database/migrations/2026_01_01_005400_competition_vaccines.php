<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vaccins reçus lors d'une vaccination (grippe, rhinopneumonie…) ; vide : reconnus dans le texte du soin.
        Schema::table('care_records', function (Blueprint $table) {
            $table->json('vaccines')->nullable()->after('care_performed');
        });
        // Règlement des vaccins d'un concours (FFE, FEI…) ; vide : règlement par défaut, « none » : pas de vérification.
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->string('vaccine_rules', 30)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', fn (Blueprint $table) => $table->dropColumn('vaccine_rules'));
        Schema::table('care_records', fn (Blueprint $table) => $table->dropColumn('vaccines'));
    }
};
