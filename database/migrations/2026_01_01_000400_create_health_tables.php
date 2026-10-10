<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Répertoire des intervenants, dans le périmètre d'une organisation (personnelle ou écurie).
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name');
            $table->string('company')->nullable();
            $table->enum('kind', ['veterinarian', 'farrier', 'osteopath', 'dentist', 'instructor', 'saddler', 'nutritionist', 'other'])->default('other');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('usual_rate', 10, 2)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'kind']);
            $table->index(['organization_id', 'last_name']);
        });

        Schema::create('horse_professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['horse_id', 'professional_id']);
        });

        Schema::create('care_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete(); // NULL = catégorie par défaut
            $table->string('key', 40)->nullable();
            $table->string('name');
            $table->string('color', 20)->default('slate');
            $table->timestamps();
        });

        Schema::create('care_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_category_id')->constrained();
            $table->foreignId('professional_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at');
            $table->string('reason')->nullable();
            $table->text('observations')->nullable();
            $table->text('care_performed')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->date('next_check_on')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['horse_id', 'performed_at']);
            $table->index('next_check_on');
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product');
            $table->string('dosage')->nullable();      // posologie prescrite (texte saisi, jamais calculée)
            $table->string('frequency')->nullable();
            $table->unsignedSmallInteger('times_per_day')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('track_administrations')->default(false);
            $table->enum('status', ['active', 'completed', 'stopped'])->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['horse_id', 'status']);
        });

        Schema::create('treatment_administrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('administered_at');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('health_observations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('observed_at');
            $table->enum('severity', ['info', 'watch', 'alert'])->default('info');
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['horse_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        foreach (['health_observations', 'treatment_administrations', 'treatments', 'care_records', 'care_categories', 'horse_professionals', 'professionals'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
