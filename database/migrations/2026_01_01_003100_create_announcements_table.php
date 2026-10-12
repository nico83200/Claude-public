<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages ponctuels du super-administrateur (information, offre) envoyés en notification. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->string('url', 500)->nullable();
            $table->json('plan_ids')->nullable(); // null = tous les utilisateurs
            $table->boolean('with_email')->default(false);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
