<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recettes récurrentes (demi-pension chaque mois…) et suivi « reçue » des recettes,
 * comme les dépenses récurrentes et le « payée » des dépenses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30);
            $table->string('label', 120);
            $table->decimal('amount', 10, 2);
            $table->string('frequency', 20);
            $table->date('starts_on');
            $table->date('next_due_on');
            $table->date('ends_on')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->string('payer', 150)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['horse_id', 'next_due_on']);
        });
        Schema::table('incomes', function (Blueprint $table) {
            $table->date('collected_on')->nullable()->after('received_on');
            $table->foreignId('recurring_income_id')->nullable()->after('author_id')->constrained()->nullOnDelete();
            $table->unique(['recurring_income_id', 'received_on']);
        });
        // Recettes déjà saisies : considérées comme reçues à leur date.
        DB::table('incomes')->whereNull('collected_on')->update(['collected_on' => DB::raw('received_on')]);
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['recurring_income_id', 'received_on']);
            $table->dropConstrainedForeignId('recurring_income_id');
            $table->dropColumn('collected_on');
        });
        Schema::dropIfExists('recurring_incomes');
    }
};
