<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Carte de cours (N cours payés d'avance) ou forfait mensuel (N cours par mois) d'une personne, avec un enseignant, pour un cheval.
        Schema::create('lesson_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->unsignedSmallInteger('lessons');
            $table->decimal('price', 8, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payer_id', 'professional_id']);
        });
        // Cours décompté d'une carte ou d'un forfait, ou frais de la carte / du mois.
        Schema::table('lesson_charges', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('professional_id')->constrained('lesson_packages')->nullOnDelete();
        });
        // Annulation tardive : cours dû s'il est annulé moins de N heures avant.
        Schema::table('professionals', function (Blueprint $table) {
            $table->unsignedSmallInteger('cancel_notice_hours')->nullable()->after('usual_rate');
        });
        Schema::table('riding_sessions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('riding_sessions', fn (Blueprint $table) => $table->dropColumn('cancelled_at'));
        Schema::table('professionals', fn (Blueprint $table) => $table->dropColumn('cancel_notice_hours'));
        Schema::table('lesson_charges', fn (Blueprint $table) => $table->dropConstrainedForeignId('package_id'));
        Schema::dropIfExists('lesson_packages');
    }
};
