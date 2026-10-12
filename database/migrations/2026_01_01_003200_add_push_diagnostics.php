<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Diagnostic des notifications : dernière erreur par appareil, appareils atteints par message. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->timestamp('last_failure_at')->nullable()->after('failures');
            $table->string('last_error', 255)->nullable()->after('last_failure_at');
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->unsignedInteger('devices_reached')->default(0)->after('recipients_count');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_failure_at', 'last_error']);
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('devices_reached');
        });
    }
};
