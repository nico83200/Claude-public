<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2.7 : vidéos affichées automatiquement — tutoriel à la première connexion, nouveautés à la
 * connexion suivante de chaque utilisateur. Voix off (piste audio séparée) et sous-titres facultatifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->enum('kind', ['onboarding', 'announcement'])->default('announcement');
            // « builtin » : fichiers livrés avec l'application (public/videos), sinon envoyés par le super-administrateur (stockage privé).
            $table->string('source', 20)->default('upload');
            $table->string('video_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('captions_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['video_id', 'user_id']);
        });

        // Tutoriel livré avec l'application : proposé à la première connexion des nouveaux comptes.
        DB::table('videos')->insert([
            'title' => 'Bienvenue dans Jack&Cie', 'description' => 'Découvrez l\'application en deux minutes : accueil, assistant vocal, fiche cheval, séances, calendrier et budget.',
            'kind' => 'onboarding', 'source' => 'builtin',
            'video_path' => 'videos/tutoriel-mobile.mp4', 'captions_path' => 'videos/tutoriel-mobile.vtt', 'poster_path' => 'videos/tutoriel-mobile.jpg',
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('video_views');
        Schema::dropIfExists('videos');
    }
};
