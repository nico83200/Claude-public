<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2.8 : vidéos ciblées par appareil (téléphone/tablette, ordinateur, ou les deux) et tutoriel version ordinateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('device', 10)->default('all')->after('kind');
        });
        $mobile = DB::table('videos')->where('source', 'builtin')->where('video_path', 'videos/tutoriel-mobile.mp4')->first();
        if ($mobile) {
            DB::table('videos')->where('id', $mobile->id)->update(['device' => 'mobile', 'title' => 'Bienvenue dans Jack&Cie']);
        }
        DB::table('videos')->insert([
            'title' => 'Bienvenue dans Jack&Cie', 'description' => 'Découvrez l\'application sur ordinateur en deux minutes : accueil, assistant, fiche cheval, séances, calendrier et budget.',
            'kind' => 'onboarding', 'device' => 'desktop', 'source' => 'builtin',
            'video_path' => 'videos/tutoriel-ordinateur.mp4', 'captions_path' => 'videos/tutoriel-ordinateur.vtt', 'poster_path' => 'videos/tutoriel-ordinateur.jpg',
            // Même date que le tutoriel mobile : les comptes qui ont reçu l'un recevront l'autre sur ordinateur.
            'published_at' => $mobile?->published_at ?? now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('videos')->where('video_path', 'videos/tutoriel-ordinateur.mp4')->delete();
        Schema::table('videos', fn (Blueprint $table) => $table->dropColumn('device'));
    }
};
