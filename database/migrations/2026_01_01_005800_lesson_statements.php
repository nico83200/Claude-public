<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cours donnés par un enseignant de l'écurie : repris sur le relevé mensuel de la personne qui paie.
        Schema::table('lesson_charges', function (Blueprint $table) {
            $table->foreignId('boarding_statement_id')->nullable()->after('expense_id')->constrained('boarding_statements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_charges', fn (Blueprint $table) => $table->dropConstrainedForeignId('boarding_statement_id'));
    }
};
