<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes, organisations, rôles et permissions.
 *
 * Chaque utilisateur possède un espace personnel (organization.type = personal)
 * créé à l'inscription. Les écuries sont des organisations de type "stable".
 * L'abonnement et la licence sont rattachés à une organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->timestamp('suspended_at')->nullable()->after('is_super_admin');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
            $table->foreignId('current_organization_id')->nullable()->after('suspension_reason');
            $table->string('locale', 8)->default('fr')->after('current_organization_id');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('city')->nullable();
            $table->string('riding_level', 60)->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['personal', 'stable'])->default('personal');
            $table->foreignId('owner_id')->constrained('users');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address_line')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('FR');
            $table->json('settings')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'owner_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_organization_id')->references('id')->on('organizations')->nullOnDelete();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label');
            $table->string('group', 40);
            $table->timestamps();
        });

        // Rôles d'organisation. organization_id NULL = rôle système (modèle).
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->string('title')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        // Rôles globaux (plateforme). Le super-administrateur reste porté par
        // users.is_super_admin, créé uniquement en ligne de commande.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['current_organization_id']));
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('user_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'suspended_at', 'suspension_reason', 'current_organization_id', 'locale', 'last_login_at', 'terms_accepted_at', 'deletion_requested_at', 'deleted_at']);
        });
    }
};
