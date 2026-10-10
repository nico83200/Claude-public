<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeding_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('instructions')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // un nouveau plan clôt le précédent (historique conservé)
            $table->timestamps();
            $table->index(['horse_id', 'ends_on']);
        });

        Schema::create('feeding_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feeding_plan_id')->constrained()->cascadeOnDelete();
            $table->string('feed');
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit', 20)->default('kg');
            $table->time('time_of_day')->nullable();
            $table->string('frequency', 60)->nullable();
            $table->boolean('is_supplement')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('logged_at');
            $table->enum('appetite', ['good', 'reduced', 'none'])->nullable();
            $table->enum('general_state', ['good', 'average', 'poor'])->nullable();
            $table->string('behavior')->nullable();
            $table->string('activity')->nullable();
            $table->text('observations')->nullable();
            $table->text('anomalies')->nullable();
            $table->timestamps();
            $table->index(['horse_id', 'logged_at']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('horse_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('professional_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('source'); // soin, traitement, séance générant l'événement
            $table->enum('type', ['veterinary', 'farrier', 'care', 'treatment', 'session', 'lesson', 'competition', 'ride', 'rest', 'custom']);
            $table->string('title');
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('all_day')->default(false);
            $table->enum('status', ['planned', 'confirmed', 'done', 'cancelled'])->default('planned');
            $table->text('notes')->nullable();
            $table->string('recurrence', 20)->nullable(); // daily|weekly|monthly
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->date('recurrence_until')->nullable();
            $table->string('external_uid')->nullable()->unique(); // future synchro externe, anti-doublon
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'starts_at']);
            $table->index(['horse_id', 'starts_at']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->dateTime('remind_at');
            $table->enum('channel', ['app', 'email', 'both'])->default('app');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'remindable_type', 'remindable_id', 'remind_at'], 'reminders_unique');
            $table->index(['sent_at', 'remind_at']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained();
            $table->foreignId('professional_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('care_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('spent_on');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->string('supplier')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'spent_on']);
            $table->index(['horse_id', 'spent_on']);
        });

        Schema::create('expense_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });

        // Les notifications applicatives utilisent la table standard Laravel.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->enum('type', ['export', 'rectification', 'deletion']);
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'in_progress', 'done', 'rejected'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['data_requests', 'notifications', 'expense_documents', 'expenses', 'expense_categories', 'reminders', 'calendar_events', 'daily_logs', 'feeding_entries', 'feeding_plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
