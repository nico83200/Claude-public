<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('wipe_requested_at')->nullable(); // purge demandée (révocation, déconnexion à distance)
            $table->timestamps();
            $table->unique(['user_id', 'device_uuid']);
        });

        // Journal idempotent : chaque opération client a un op_uuid unique.
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('op_uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity', 40);
            $table->uuid('entity_uuid')->nullable();
            $table->string('action', 40);
            $table->json('payload');
            $table->enum('status', ['applied', 'duplicate', 'rejected', 'conflict']);
            $table->json('result')->nullable();
            $table->timestamp('client_created_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_device_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 40);
            $table->timestamp('cursor')->nullable();
            $table->timestamps();
            $table->unique(['sync_device_id', 'scope']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('op_uuid');
            $table->string('entity', 40);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->uuid('entity_uuid')->nullable();
            $table->json('local_values');
            $table->json('server_values');
            $table->unsignedInteger('base_version')->nullable();
            $table->unsignedInteger('server_version')->nullable();
            $table->enum('status', ['open', 'resolved_local', 'resolved_server', 'dismissed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['sync_conflicts', 'sync_cursors', 'sync_operations', 'sync_devices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
