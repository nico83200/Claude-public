<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sauvegardes automatiques hors de l'hébergement (Google Drive) : une ligne par archive envoyée ou tentée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // database | files
            $table->string('status', 20)->default('running'); // running | done | failed
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('drive_file_id')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
