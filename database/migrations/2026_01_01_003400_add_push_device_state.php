<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** État remonté par l'appareil : autorisation, version du service worker, essai d'affichage local. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('device_permission', 20)->nullable()->after('last_display_error');
            $table->string('sw_version', 10)->nullable()->after('device_permission');
            $table->string('local_test', 255)->nullable()->after('sw_version');
            $table->timestamp('device_checked_at')->nullable()->after('local_test');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['device_permission', 'sw_version', 'local_test', 'device_checked_at']);
        });
    }
};
