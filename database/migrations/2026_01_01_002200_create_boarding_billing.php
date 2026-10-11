<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturation de la pension (espace écurie) : contrat par cheval (montant mensuel, propriétaire facturé),
 * prestations ponctuelles (ferrure avancée, sortie au paddock, aliment complémentaire…) et relevés
 * mensuels par propriétaire, avec suivi du paiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name', 150);
            $table->string('owner_email')->nullable();
            $table->string('label', 150)->default('Pension');
            $table->decimal('monthly_amount', 9, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'ends_on']);
        });
        Schema::create('boarding_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->char('period', 7); // AAAA-MM
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name', 150);
            $table->string('owner_email')->nullable();
            $table->json('lines');
            $table->decimal('total', 10, 2);
            $table->string('status', 10)->default('draft'); // draft | sent | paid
            $table->timestamp('sent_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'period']);
        });
        Schema::create('boarding_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->date('charged_on');
            $table->string('label', 150);
            $table->decimal('quantity', 7, 2)->default(1);
            $table->decimal('unit_price', 9, 2);
            $table->foreignId('statement_id')->nullable()->constrained('boarding_statements')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'charged_on']);
        });
        Schema::table('expenses', function (Blueprint $table) {
            // Dépense créée chez le propriétaire à l'envoi d'un relevé de pension (payée en même temps que le relevé).
            $table->foreignId('boarding_statement_id')->nullable()->after('care_record_id')->constrained('boarding_statements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('boarding_statement_id'));
        Schema::dropIfExists('boarding_charges');
        Schema::dropIfExists('boarding_statements');
        Schema::dropIfExists('boarding_contracts');
    }
};
