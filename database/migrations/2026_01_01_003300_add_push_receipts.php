<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Accusé de réception envoyé par l'appareil quand il reçoit et affiche une notification. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->timestamp('last_received_at')->nullable()->after('last_success_at');
            $table->string('last_display_error', 255)->nullable()->after('last_received_at');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_received_at', 'last_display_error']);
        });
    }
};
