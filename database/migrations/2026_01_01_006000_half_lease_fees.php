<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensualités de demi-pension : recette mensuelle du propriétaire reliée à la fiche (échéances visibles par la demi-pension),
 * premier mois complet ou au prorata, mois sans mensualité, rappels (échéance à la demi-pension, retard au propriétaire).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('half_leases', function (Blueprint $table) {
            $table->string('first_month', 10)->default('full')->after('payment_day');
            $table->json('skipped_months')->nullable()->after('first_month');
            $table->foreignId('recurring_income_id')->nullable()->after('grant_id')->constrained()->nullOnDelete();
        });
        Schema::table('recurring_incomes', function (Blueprint $table) {
            $table->foreignId('half_lease_id')->nullable()->after('horse_id')->constrained()->nullOnDelete();
        });
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('half_lease_id')->nullable()->after('recurring_income_id')->constrained()->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('half_lease_id');
            $table->dropColumn(['notified_at', 'reminded_at']);
        });
        Schema::table('recurring_incomes', fn (Blueprint $table) => $table->dropConstrainedForeignId('half_lease_id'));
        Schema::table('half_leases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_income_id');
            $table->dropColumn(['first_month', 'skipped_months']);
        });
    }
};
