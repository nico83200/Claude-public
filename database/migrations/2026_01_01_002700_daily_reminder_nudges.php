<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Suggestion d'activer le rappel quotidien : nombre d'envois, dernier envoi, refus définitif. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('daily_nudge_count')->default(0)->after('daily_reminder_at');
            $table->timestamp('daily_nudge_at')->nullable()->after('daily_nudge_count');
            $table->timestamp('daily_nudge_stopped_at')->nullable()->after('daily_nudge_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn(['daily_nudge_count', 'daily_nudge_at', 'daily_nudge_stopped_at']));
    }
};
