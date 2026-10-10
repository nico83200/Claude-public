<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;

class SessionExercise extends Model
{
    use HasClientUuid;

    protected $guarded = ['id', 'riding_session_id'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['status' => 'pending', 'phase' => 'main', 'difficulty' => false, 'is_break' => false];

    protected $casts = ['snapshot' => 'array', 'is_break' => 'boolean', 'difficulty' => 'boolean', 'status_changed_at' => 'datetime'];

    public function session()
    {
        return $this->belongsTo(RidingSession::class, 'riding_session_id');
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }

    /** Crée une ligne figée à partir d'un exercice de la bibliothèque. */
    public static function fromExercise(Exercise $exercise, array $overrides = []): self
    {
        return new self(array_merge([
            'exercise_id' => $exercise->id,
            'exercise_version' => $exercise->version,
            'name' => $exercise->name,
            'snapshot' => $exercise->snapshot(),
            'planned_minutes' => $exercise->duration_minutes,
            'planned_repetitions' => $exercise->repetitions,
            'instructions' => $exercise->instructions,
        ], array_filter($overrides, fn ($v) => $v !== null)));
    }
}
