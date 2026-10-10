<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // scope: default (plateforme), personal (user_id), organization (organization_id)
        Schema::create('exercise_library', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('scope', ['default', 'personal', 'organization']);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('exercise_library')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discipline', 60)->nullable();
            $table->string('objective')->nullable();
            $table->enum('level', ['beginner', 'intermediate', 'advanced', 'all'])->default('all');
            $table->text('equipment')->nullable();
            $table->text('prerequisites')->nullable();
            $table->text('instructions')->nullable();
            $table->json('steps')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('repetitions')->nullable();
            $table->text('common_mistakes')->nullable();
            $table->text('vigilance')->nullable();
            $table->text('success_criteria')->nullable();
            $table->string('media_url', 1000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['scope', 'archived_at']);
            $table->index(['user_id', 'scope']);
            $table->index(['organization_id', 'scope']);
        });

        Schema::create('exercise_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('exercise_tag_assignments', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained('exercise_library')->cascadeOnDelete();
            $table->foreignId('exercise_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['exercise_id', 'exercise_tag_id']);
        });

        Schema::create('session_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('discipline', 60)->nullable();
            $table->string('session_type', 40)->nullable();
            $table->string('objective')->nullable();
            $table->unsignedSmallInteger('planned_minutes')->nullable();
            $table->json('items'); // [{exercise_id, phase, duration_minutes, repetitions, instructions, is_break}]
            $table->timestamps();
        });

        Schema::create('riding_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // généré côté client possible (hors ligne)
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rider_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('session_templates')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('discipline', 60)->nullable();
            $table->string('session_type', 40);
            $table->string('objective')->nullable();
            $table->unsignedSmallInteger('planned_minutes')->nullable();
            $table->unsignedSmallInteger('actual_minutes')->nullable();
            $table->string('location')->nullable();
            $table->string('horse_state_before')->nullable();
            $table->text('precautions')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            // Bilan
            $table->unsignedTinyInteger('rider_feeling')->nullable();     // 1..5
            $table->unsignedTinyInteger('horse_behavior')->nullable();    // 1..5
            $table->unsignedTinyInteger('concentration')->nullable();     // 1..5
            $table->unsignedTinyInteger('availability')->nullable();      // 1..5
            $table->text('difficulties')->nullable();
            $table->text('progress')->nullable();
            $table->text('to_rework')->nullable();
            $table->text('anomalies')->nullable();
            $table->text('next_objectives')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['horse_id', 'scheduled_at']);
            $table->index(['rider_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index('updated_at');
        });

        // Copie figée des paramètres de l'exercice au moment de la séance.
        Schema::create('session_exercises', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('riding_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained('exercise_library')->nullOnDelete();
            $table->unsignedInteger('exercise_version')->nullable();
            $table->enum('phase', ['warmup', 'main', 'complementary', 'cooldown'])->default('main');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_break')->default(false);
            $table->string('name');
            $table->json('snapshot')->nullable();
            $table->unsignedSmallInteger('planned_minutes')->nullable();
            $table->unsignedSmallInteger('planned_repetitions')->nullable();
            $table->text('instructions')->nullable();
            $table->enum('status', ['pending', 'done', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('done_repetitions')->nullable();
            $table->unsignedSmallInteger('actual_minutes')->nullable();
            $table->boolean('difficulty')->default(false);
            $table->string('note')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();
            $table->index(['riding_session_id', 'position']);
        });

        Schema::create('session_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('riding_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('session_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('riding_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('mime', 120);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['session_media', 'session_comments', 'session_exercises', 'riding_sessions', 'session_templates', 'exercise_tag_assignments', 'exercise_tags', 'exercise_library', 'exercise_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
