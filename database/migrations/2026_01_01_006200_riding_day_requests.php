<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Demandes de jour de monte : « puis-je monter samedi ? », éventuellement contre un de ses jours ; acceptées ou refusées d'un geste. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riding_day_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->date('swap_date')->nullable();
            $table->string('status', 12)->default('pending');
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index(['horse_id', 'status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riding_day_requests');
    }
};
