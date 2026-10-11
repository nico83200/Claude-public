<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2.4 : notifications sur le téléphone (Web Push), rappels « faire le bilan » après une séance
 * ou un cours, rappel quotidien de saisie, enseignant d'un cours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('device', 120)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamps();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->boolean('debrief_reminders')->default(true)->after('email_notifications');
            $table->time('daily_reminder_at')->nullable()->after('debrief_reminders');
        });

        Schema::table('riding_sessions', function (Blueprint $table) {
            $table->string('instructor', 100)->nullable()->after('rider_name');
        });
    }

    public function down(): void
    {
        Schema::table('riding_sessions', fn (Blueprint $table) => $table->dropColumn('instructor'));
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn(['debrief_reminders', 'daily_reminder_at']));
        Schema::dropIfExists('push_subscriptions');
    }
};
