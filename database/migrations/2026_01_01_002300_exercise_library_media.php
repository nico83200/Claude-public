<?php

use Database\Seeders\ExerciseLibrarySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exercices : clé stable des exercices par défaut (ajouts lors des mises à jour sans écraser les
 * modifications du super-administrateur) et image ou vidéo de démonstration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_library', function (Blueprint $table) {
            $table->string('seed_key', 80)->nullable()->unique()->after('uuid');
            $table->string('media_path')->nullable()->after('media_url');
            $table->enum('media_type', ['image', 'video'])->nullable()->after('media_path');
            $table->unsignedInteger('media_size')->nullable()->after('media_type');
        });

        // Exercices par défaut déjà installés : rattachés à leur clé par leur nom.
        $used = DB::table('exercise_library')->whereNotNull('seed_key')->pluck('seed_key')->flip()->all();
        $byName = collect(ExerciseLibrarySeeder::library())->mapWithKeys(fn ($e) => [$e[2] => $e[0]]);
        DB::table('exercise_library')->where('scope', 'default')->whereNull('seed_key')->orderBy('id')->get(['id', 'name'])
            ->each(function ($row) use ($byName, &$used) {
                $key = $byName[$row->name] ?? null;
                if ($key && ! isset($used[$key])) {
                    $used[$key] = true;
                    DB::table('exercise_library')->where('id', $row->id)->whereNull('seed_key')->update(['seed_key' => $key]);
                }
            });

        // Nouveaux exercices de la bibliothèque (installations existantes).
        if (DB::table('exercise_categories')->exists()) {
            (new ExerciseLibrarySeeder)->run();
        }
    }

    public function down(): void
    {
        Schema::table('exercise_library', function (Blueprint $table) {
            $table->dropUnique(['seed_key']);
            $table->dropColumn(['seed_key', 'media_path', 'media_type', 'media_size']);
        });
    }
};
