<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alimentation : plusieurs rations en même temps (matin, soir, complément sur 15 jours…), chacune avec sa
 * date de fin éventuelle, archivée automatiquement (avec notification) ou à la main.
 * Les anciennes versions d'une ration (closes par la suivante) deviennent des rations archivées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeding_plans', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('ends_on');
            $table->index(['horse_id', 'archived_at']);
        });
        DB::table('feeding_plans')->whereNotNull('ends_on')->where('ends_on', '<', now()->toDateString())
            ->update(['archived_at' => DB::raw('ends_on')]);
    }

    public function down(): void
    {
        Schema::table('feeding_plans', function (Blueprint $table) {
            $table->dropIndex(['horse_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
