<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Codes promotionnels administrables :
 *  - discount    : réduction sur l'abonnement payant (coupon + code promo Stripe) ;
 *  - free_access : accès gratuit à une offre pendant une durée (licence sans paiement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->enum('type', ['discount', 'free_access']);
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();       // NULL = illimité
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->json('plan_ids')->nullable();                         // offres éligibles (NULL = toutes)
            // Accès gratuit
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('free_days')->nullable();
            // Réduction
            $table->decimal('percent_off', 5, 2)->nullable();
            $table->decimal('amount_off', 10, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->enum('duration', ['once', 'repeating', 'forever'])->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->boolean('first_subscription_only')->default(false);
            $table->string('stripe_coupon_id')->nullable();
            $table->string('stripe_promotion_code_id')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['promo_code_id', 'organization_id']);
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE licenses MODIFY source ENUM('stripe','manual','trial','free','code') NOT NULL");
        }
        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('promo_code_id'));
        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
    }
};
