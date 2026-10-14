<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crédits IA achetés en plus du quota mensuel de l'offre : solde par espace,
 * achats payés par Stripe Checkout, et consignes réglées avec un crédit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('assistant_credits')->default(0);
        });
        Schema::table('assistant_requests', function (Blueprint $table) {
            $table->boolean('paid_with_credit')->default(false);
        });
        Schema::create('assistant_credit_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('credits');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('eur');
            $table->string('stripe_session_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending, paid
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_credit_purchases');
        Schema::table('assistant_requests', fn (Blueprint $table) => $table->dropColumn('paid_with_credit'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('assistant_credits'));
    }
};
