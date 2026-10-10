<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\Versioned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RidingSession extends Model
{
    use HasClientUuid, SoftDeletes, Versioned;

    protected $guarded = ['id', 'horse_id', 'created_by', 'version'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['version' => 1, 'status' => 'planned'];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];

    public const TYPES = [
        'free' => 'Monte libre', 'private_lesson' => 'Cours particulier', 'group_lesson' => 'Cours collectif',
        'groundwork' => 'Travail à pied', 'lunging' => 'Longe', 'liberty' => 'Liberté', 'dressage' => 'Dressage',
        'jumping' => 'Obstacle', 'poles' => 'Barres au sol', 'ride' => 'Balade', 'outdoor' => 'Extérieur',
        'recovery' => 'Récupération', 'active_rest' => 'Repos actif', 'other' => 'Autre',
    ];

    public const STATUSES = ['planned' => 'Prévue', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée'];

    public const PHASES = ['warmup' => 'Échauffement', 'main' => 'Travail principal', 'complementary' => 'Travail complémentaire', 'cooldown' => 'Retour au calme'];

    /** Champs du bilan. */
    public const DEBRIEF_FIELDS = ['actual_minutes', 'rider_feeling', 'horse_behavior', 'concentration', 'availability', 'difficulties', 'progress', 'to_rework', 'anomalies', 'next_objectives'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exercises()
    {
        return $this->hasMany(SessionExercise::class)->orderByRaw("CASE phase WHEN 'warmup' THEN 0 WHEN 'main' THEN 1 WHEN 'complementary' THEN 2 ELSE 3 END")->orderBy('position');
    }

    public function items()
    {
        return $this->hasMany(SessionExercise::class);
    }

    public function comments()
    {
        return $this->hasMany(SessionComment::class)->oldest();
    }

    public function media()
    {
        return $this->hasMany(SessionMedia::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->session_type] ?? $this->session_type;
    }

    public function riderLabel(): string
    {
        return $this->rider?->name ?? $this->rider_name ?? '—';
    }
}
