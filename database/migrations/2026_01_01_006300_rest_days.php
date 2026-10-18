<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jours de repos du cheval dans les jours de monte : dans la semaine type (sans cavalier) ou pour une date. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horse_riding_days', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->boolean('is_rest')->default(false)->after('user_id');
        });
        Schema::table('horse_riding_day_changes', function (Blueprint $table) {
            $table->boolean('is_rest')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('horse_riding_day_changes', fn (Blueprint $table) => $table->dropColumn('is_rest'));
        Schema::table('horse_riding_days', function (Blueprint $table) {
            $table->dropColumn('is_rest');
        });
    }
};
