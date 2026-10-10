<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offres, abonnements Stripe, licences applicatives, paiements.
 *
 * Séparation stricte :
 *  - subscription_plans : l'offre commerciale (administrable)
 *  - subscriptions      : la relation de facturation Stripe
 *  - licenses           : les droits effectifs (peut exister sans Stripe : attribution manuelle)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->enum('audience', ['individual', 'stable', 'any'])->default('any');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_default_free')->default(false);
            $table->unsignedInteger('max_horses')->nullable();      // NULL = illimité
            $table->unsignedInteger('max_members')->nullable();
            $table->unsignedInteger('max_invitations')->nullable();
            $table->unsignedInteger('storage_mb')->nullable();
            $table->unsignedInteger('history_months')->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->text('renewal_terms')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // Historique des changements de prix (aucune modification silencieuse).
        Schema::create('plan_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_price_monthly', 10, 2)->nullable();
            $table->decimal('new_price_monthly', 10, 2)->nullable();
            $table->decimal('old_price_yearly', 10, 2)->nullable();
            $table->decimal('new_price_yearly', 10, 2)->nullable();
            $table->string('old_stripe_price_monthly_id')->nullable();
            $table->string('old_stripe_price_yearly_id')->nullable();
            $table->enum('existing_subscribers', ['keep_old_price', 'migrate_after_notice'])->default('keep_old_price');
            $table->date('migration_effective_on')->nullable();
            $table->timestamp('subscribers_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stripe_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('stripe_customer_id')->unique();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable();
            $table->enum('interval', ['month', 'year'])->default('month');
            $table->decimal('unit_amount', 10, 2)->nullable(); // prix verrouillé à la souscription
            $table->char('currency', 3)->default('EUR');
            $table->string('stripe_status', 40)->default('incomplete');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('last_stripe_event_created')->nullable(); // anti-désordre
            $table->timestamps();
            $table->index(['organization_id', 'stripe_status']);
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source', ['stripe', 'manual', 'trial', 'free']);
            $table->enum('status', ['pending', 'active', 'grace', 'cancel_scheduled', 'expired', 'suspended', 'blocked'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->json('limit_overrides')->nullable();
            $table->json('feature_overrides')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type', 100);
            $table->unsignedBigInteger('stripe_created');
            $table->boolean('livemode')->default(false);
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'ignored', 'failed'])->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('number')->nullable();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('amount_refunded', 10, 2)->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['draft', 'open', 'paid', 'failed', 'void', 'uncollectible', 'refunded'])->default('open');
            $table->string('hosted_invoice_url', 1000)->nullable();
            $table->string('invoice_pdf', 1000)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('enabled_globally')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['feature_flags', 'application_settings', 'payment_records', 'stripe_events', 'licenses', 'subscriptions', 'stripe_customers', 'plan_price_changes', 'subscription_plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
