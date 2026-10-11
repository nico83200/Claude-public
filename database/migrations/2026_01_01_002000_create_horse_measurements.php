<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Suivi du poids et de l'état corporel (note de Henneke 1 à 9), avec mesures au ruban facultatives. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('method', 10)->default('scale'); // scale | tape | estimate
            $table->unsignedSmallInteger('girth_cm')->nullable();
            $table->unsignedSmallInteger('length_cm')->nullable();
            $table->unsignedTinyInteger('body_condition')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['horse_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horse_measurements');
    }
};
