<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** « Ma semaine » : introduction rédigée par l'IA, mémorisée avec l'empreinte du contenu de la semaine. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digest_intros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->text('text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digest_intros');
    }
};
