<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier du cheval, propriété, rattachements aux organisations et partages.
 *
 * Un cheval = un seul enregistrement `horses`. Les liens avec les personnes et
 * les organisations sont portés par des tables de relation datées, ce qui évite
 * toute duplication lors d'un changement d'écurie ou de propriétaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horse_breeds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('horses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Espace gestionnaire : l'organisation dont la licence couvre ce cheval.
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('official_name');
            $table->string('usual_name')->nullable();
            $table->foreignId('horse_breed_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('sex', ['male', 'female', 'gelding', 'unknown'])->default('unknown');
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('coat', 80)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->char('birth_country', 2)->nullable();
            $table->string('breeder')->nullable();
            $table->string('main_discipline', 60)->nullable();
            $table->string('work_level', 60)->nullable();
            $table->text('particularities')->nullable();
            $table->text('general_notes')->nullable();
            $table->text('care_instructions')->nullable();   // consignes visibles des cavaliers autorisés
            $table->text('precautions')->nullable();
            $table->string('main_photo_path')->nullable();
            $table->string('current_location')->nullable();
            $table->unsignedInteger('version')->default(1); // verrou optimiste (sync)
            $table->boolean('is_demo')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'archived_at']);
            $table->index('official_name');
            $table->index('updated_at');
        });

        Schema::create('horse_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['sire', 'ueln', 'transponder', 'passport', 'fei', 'other']);
            $table->string('value', 64);
            $table->timestamps();
            $table->unique(['horse_id', 'type']);
            $table->index(['type', 'value']);
        });

        Schema::create('horse_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('studbook')->nullable();
            $table->string('bloodline')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Généalogie : distincte des relations de propriété / partage.
        Schema::create('horse_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->enum('relation', ['sire', 'dam', 'sire_sire', 'sire_dam', 'dam_sire', 'dam_dam']);
            $table->foreignId('related_horse_id')->nullable()->constrained('horses')->nullOnDelete();
            $table->string('related_name')->nullable();
            $table->string('related_breed')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamps();
            $table->unique(['horse_id', 'relation']);
        });

        Schema::create('horse_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->boolean('available_offline')->default(false);
            $table->timestamps();
        });

        Schema::create('horse_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('attachable'); // soin, dépense...
            $table->enum('category', ['report', 'prescription', 'invoice', 'exam', 'identification', 'photo', 'other'])->default('other');
            $table->string('title');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedInteger('size_bytes');
            $table->boolean('is_sensitive')->default(true); // nécessite health.view
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('horse_external_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->string('source', 60);
            $table->string('url', 1000)->nullable();
            $table->string('field', 60);
            $table->text('value')->nullable();
            $table->timestamp('fetched_at');
            $table->enum('reliability', ['official', 'reference', 'suggested'])->default('suggested');
            $table->enum('validation', ['confirmed', 'pending', 'rejected'])->default('pending');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['horse_id', 'field']);
        });

        Schema::create('horse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('address')->nullable();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
        });

        Schema::create('horse_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_name')->nullable(); // propriétaire sans compte
            $table->enum('role', ['owner', 'co_owner', 'keeper'])->default('owner');
            $table->decimal('share_percent', 5, 2)->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'ended_on']);
        });

        // Prise en charge par une organisation tierce (pension, écurie).
        // Les droits de l'écurie sont limités par `permissions` (définies par le propriétaire).
        Schema::create('horse_organization_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['boarding', 'training', 'care', 'other'])->default('boarding');
            $table->json('permissions');
            $table->enum('status', ['pending', 'active', 'ended', 'declined'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('email');
            $table->enum('type', ['horse_access', 'organization_member']);
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->json('permissions')->nullable();
            $table->timestamp('access_starts_at')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'accepted_at']);
        });

        // Partage direct (demi-pension, cavalier) avec droits explicites.
        Schema::create('horse_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->nullable(); // ex. "Demi-pension"
            $table->json('permissions');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
            $table->index(['horse_id', 'revoked_at']);
        });

        // Sélection des chevaux disponibles hors ligne, par utilisateur.
        Schema::create('offline_horse_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('horse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'horse_id']);
        });
    }

    public function down(): void
    {
        foreach (['offline_horse_selections', 'horse_access_grants', 'invitations', 'horse_organization_assignments', 'horse_ownerships', 'horse_locations', 'horse_external_sources', 'horse_documents', 'horse_photos', 'horse_relationships', 'horse_origins', 'horse_identifiers', 'horses', 'horse_breeds'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
