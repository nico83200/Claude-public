<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance des inscriptions : scans de QR code et clics sur les liens d'inscription (affiches,
 * Instagram…). Aucune donnée personnelle : le visiteur est un condensat anonyme de la session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisition_visits', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);
            $table->string('code', 40)->nullable();
            $table->char('visitor', 64);
            $table->unsignedInteger('hits')->default(1);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('code_redeemed_at')->nullable();
            $table->timestamps();
            $table->unique(['visitor', 'source', 'code']);
            $table->index(['source', 'created_at']);
            $table->index(['code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_visits');
    }
};
