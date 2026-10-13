<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Résumé de santé partagé par lien temporaire (vétérinaire, ostéopathe…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 48)->unique();
            $table->string('recipient', 120)->nullable();
            $table->unsignedTinyInteger('months')->default(6);
            $table->text('ai_summary')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->index(['horse_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_shares');
    }
};
