<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rappel régulier de pesée : toutes les N semaines depuis la dernière mesure. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->unsignedSmallInteger('weigh_every_weeks')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->dropColumn('weigh_every_weeks');
        });
    }
};
