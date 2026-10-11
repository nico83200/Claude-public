<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2.5 : dépenses récurrentes (pension…) avec suivi des paiements, dernière visite des utilisateurs
 * (rappel après X heures sans ouvrir l'application).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('professional_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label', 120);
            $table->decimal('amount', 10, 2);
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_due_on');
            $table->string('supplier', 150)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'next_due_on']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('recurring_expense_id')->nullable()->after('care_record_id')->constrained()->nullOnDelete();
            $table->date('paid_on')->nullable()->after('amount');
            $table->unique(['recurring_expense_id', 'spent_on']);
        });
        // Les dépenses saisies jusqu'ici sont des dépenses réglées.
        DB::table('expenses')->whereNull('paid_on')->update(['paid_on' => DB::raw('spent_on')]);

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_seen_at'));
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['recurring_expense_id', 'spent_on']);
            $table->dropConstrainedForeignId('recurring_expense_id');
            $table->dropColumn('paid_on');
        });
        Schema::dropIfExists('recurring_expenses');
    }
};
