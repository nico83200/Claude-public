<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Apparence choisie par l'utilisateur : clair (défaut), sombre ou automatique (selon le téléphone). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('theme', 10)->default('light');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn('theme'));
    }
};
