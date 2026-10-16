<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recettes du budget : demi-pension, gains de concours, vente… rattachées à un cheval, comme les dépenses. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Toujours rattachée à un cheval : supprimée avec lui (purge définitive).
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30);
            $table->date('received_on');
            $table->decimal('amount', 10, 2);
            $table->string('payer', 150)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['horse_id', 'received_on']);
            $table->index(['organization_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
