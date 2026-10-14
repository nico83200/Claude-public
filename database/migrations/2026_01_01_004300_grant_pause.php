<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Partage d'un cheval : la personne invitée peut suspendre (puis reprendre) son propre accès. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horse_access_grants', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('horse_access_grants', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });
    }
};
