<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tarifs d'un enseignant : cours particulier (séance ou heure), cours collectif (par cavalier), coaching (jour ou parcours), déplacement.
        Schema::create('instructor_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('unit', 10)->default('session');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('label', 80)->nullable();
            $table->timestamps();
            $table->index(['professional_id', 'kind']);
        });
        // Séance : enseignant relié à sa fiche, personne qui paie, prix saisi (0 : cours offert).
        Schema::table('riding_sessions', function (Blueprint $table) {
            $table->foreignId('professional_id')->nullable()->after('instructor')->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->after('professional_id')->constrained('users')->nullOnDelete();
            $table->decimal('lesson_price', 8, 2)->nullable()->after('payer_id');
        });
        // Concours coaché : nombre de parcours (coaching facturé au parcours).
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('coaching_rounds')->nullable()->after('vaccine_rules');
        });
        // Frais de cours de chaque personne ; recopiés dans le budget du cheval seulement quand la personne en est le propriétaire.
        Schema::create('lesson_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('riding_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('label', 150);
            $table->decimal('amount', 8, 2);
            $table->date('charged_on');
            $table->date('paid_on')->nullable();
            $table->boolean('removed_with_source')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payer_id', 'charged_on']);
        });
        DB::table('expense_categories')->updateOrInsert(['key' => 'lessons'], ['name' => 'Cours et coaching', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_charges');
        Schema::table('calendar_events', fn (Blueprint $table) => $table->dropColumn('coaching_rounds'));
        Schema::table('riding_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professional_id');
            $table->dropConstrainedForeignId('payer_id');
            $table->dropColumn('lesson_price');
        });
        Schema::dropIfExists('instructor_rates');
    }
};
