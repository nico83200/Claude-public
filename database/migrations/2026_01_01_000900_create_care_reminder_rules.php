<?php

use App\Models\RidingSession;
use App\Observers\RidingSessionObserver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fréquence de suivi par cheval et par catégorie de soin (maréchal, vaccination…).
        // Sans ligne, les valeurs par défaut de config/equine.php s'appliquent (rappels actifs par défaut).
        Schema::create('care_reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('interval_value');
            $table->enum('interval_unit', ['weeks', 'months']);
            $table->unsignedSmallInteger('notice_days')->default(7);
            $table->timestamps();
            $table->unique(['horse_id', 'care_category_id']);
        });

        Schema::table('care_records', function (Blueprint $table) {
            // Contrôle remplacé par un soin plus récent de la même catégorie : plus de rappel.
            $table->timestamp('next_check_done_at')->nullable()->after('next_check_on');
        });

        // Séances existantes : ajout de leur événement au calendrier.
        $observer = app(RidingSessionObserver::class);
        RidingSession::with('horse')->chunkById(200, fn ($sessions) => $sessions->each(fn ($s) => $observer->saved($s)));
    }

    public function down(): void
    {
        Schema::dropIfExists('care_reminder_rules');
        Schema::table('care_records', fn (Blueprint $table) => $table->dropColumn('next_check_done_at'));
    }
};
