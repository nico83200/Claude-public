<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Jours de monte d'un cheval partagé (demi-pension) : à qui revient chaque jour de la semaine, et les échanges ponctuels. */
    public function up(): void
    {
        Schema::create('horse_riding_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = lundi … 7 = dimanche
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['horse_id', 'weekday']);
        });
        Schema::create('horse_riding_day_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // null : jour laissé libre
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['horse_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_riding_day_changes');
        Schema::dropIfExists('horse_riding_days');
    }
};
