<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mises à jour de l'application installées depuis l'administration.
        Schema::create('app_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_version', 40)->nullable();
            $table->string('to_version', 40)->nullable();
            $table->enum('status', ['uploaded', 'verified', 'rejected', 'applying', 'migrating', 'done', 'failed', 'restored'])->default('uploaded');
            $table->string('package_path')->nullable();
            $table->string('package_name')->nullable();
            $table->unsignedBigInteger('package_size')->default(0);
            $table->char('package_sha256', 64)->nullable();
            $table->string('backup_code_path')->nullable();
            $table->string('backup_db_path')->nullable();
            $table->string('recovery_secret', 64)->nullable();
            $table->text('message')->nullable();
            $table->longText('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_updates');
    }
};
