<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Accueil : proposition de préparer la fiche d'urgence, masquable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->timestamp('emergency_cta_dismissed_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn('emergency_cta_dismissed_at'));
    }
};
