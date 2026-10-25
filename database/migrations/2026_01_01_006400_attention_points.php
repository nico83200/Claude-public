<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points d'attention : une observation devient un dossier suivi (titre, nouvelles successives, photos,
 * relance « Des nouvelles ? » par notification, clôture avec un mot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_observations', function (Blueprint $table) {
            $table->string('title', 120)->nullable()->after('severity');
            $table->timestamp('follow_up_at')->nullable()->after('resolved_at');
            $table->unsignedTinyInteger('follow_up_count')->default(0)->after('follow_up_at');
            $table->foreignId('resolved_by')->nullable()->after('follow_up_count')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by');
            $table->index(['resolved_at', 'follow_up_at']);
        });

        Schema::create('health_observation_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_observation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('trend', ['better', 'same', 'worse'])->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['health_observation_id', 'created_at'], 'hou_observation_created_idx');
        });

        Schema::create('health_observation_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_observation_id')->constrained(indexName: 'hop_observation_fk')->cascadeOnDelete();
            $table->foreignId('health_observation_update_id')->nullable()->constrained(indexName: 'hop_update_fk')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_observation_photos');
        Schema::dropIfExists('health_observation_updates');
        Schema::table('health_observations', function (Blueprint $table) {
            $table->dropIndex(['resolved_at', 'follow_up_at']);
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['title', 'follow_up_at', 'follow_up_count', 'resolution_note']);
        });
    }
};
