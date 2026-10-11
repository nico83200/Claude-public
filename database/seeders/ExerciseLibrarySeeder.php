<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\ExerciseTag;
use Illuminate\Database\Seeder;

/**
 * Bibliothèque d'exercices par défaut (contenu pédagogique générique, modifiable par duplication,
 * et par le super-administrateur). Idempotent : chaque exercice est identifié par sa clé ; un exercice
 * déjà présent n'est jamais réécrit, pour conserver les modifications faites dans l'administration.
 */
class ExerciseLibrarySeeder extends Seeder
{
    public const CATEGORIES = [
        'warmup' => 'Échauffement', 'transitions' => 'Transitions', 'flatwork' => 'Travail sur le plat',
        'figures' => 'Figures de manège', 'straightness' => 'Rectitude', 'balance' => 'Équilibre',
        'impulsion' => 'Impulsion', 'suppling' => 'Assouplissement', 'lateral' => 'Mobilité latérale',
        'jumping' => 'Obstacle', 'poles' => 'Barres au sol', 'groundwork' => 'Travail à pied',
        'lunging' => 'Longe', 'outdoor' => 'Extérieur', 'cooldown' => 'Retour au calme',
    ];

    public static function library(): array
    {
        return require database_path('data/exercise-library.php');
    }

    public function run(): void
    {
        $i = 0;
        foreach (self::CATEGORIES as $key => $name) {
            ExerciseCategory::firstOrCreate(['key' => $key], ['name' => $name, 'sort_order' => $i]);
            $i++;
        }
        $cat = ExerciseCategory::pluck('id', 'key');
        $existing = Exercise::where('scope', 'default')->whereNotNull('seed_key')->pluck('seed_key')->flip();

        foreach (self::library() as [$key, $catKey, $name, $discipline, $level, $minutes, $reps, $objective, $instructions, $success, $vigilance, $tags]) {
            if (isset($existing[$key])) {
                continue;
            }
            $exercise = new Exercise;
            $exercise->forceFill([
                'scope' => 'default',
                'seed_key' => $key,
                'name' => $name,
                'exercise_category_id' => $cat[$catKey] ?? null,
                'discipline' => $discipline,
                'level' => $level,
                'duration_minutes' => $minutes,
                'repetitions' => $reps,
                'objective' => $objective,
                'description' => $objective,
                'instructions' => $instructions,
                'success_criteria' => $success,
                'vigilance' => $vigilance,
            ])->save();
            $exercise->tags()->sync(collect($tags)->map(fn ($t) => ExerciseTag::firstOrCreate(['name' => $t])->id));
        }
    }
}
