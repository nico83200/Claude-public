<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\Versioned;
use App\Observers\RidingSessionObserver;
use App\Support\TimeStatus;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(RidingSessionObserver::class)]
class RidingSession extends Model
{
    use HasClientUuid, SoftDeletes, Versioned;

    protected $guarded = ['id', 'horse_id', 'created_by', 'version'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['version' => 1, 'status' => 'planned'];

    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];

    /** Types proposés à la saisie d'une séance. */
    public const TYPES = ['private_lesson' => 'Cours particulier', 'group_lesson' => 'Cours collectif', 'free' => 'Monte libre', 'ride' => 'Balade'];

    /** Anciens types, plus proposés : conservés pour les séances déjà enregistrées (affichage, modification). */
    public const LEGACY_TYPES = [
        'groundwork' => 'Travail à pied', 'lunging' => 'Longe', 'liberty' => 'Liberté', 'dressage' => 'Dressage',
        'jumping' => 'Obstacle', 'poles' => 'Barres au sol', 'outdoor' => 'Extérieur',
        'recovery' => 'Récupération', 'active_rest' => 'Repos actif', 'other' => 'Autre',
    ];

    /** Cours et balade : pas de choix d'exercices à la préparation (l'enseignant ou la sortie décident du contenu). */
    public const WITHOUT_EXERCISES = ['private_lesson', 'group_lesson', 'ride'];

    /** Tous les intitulés (types actuels et anciens). */
    public static function allTypes(): array
    {
        return self::TYPES + self::LEGACY_TYPES;
    }

    /** Choix proposés pour une séance : les types actuels, plus l'ancien type de la séance s'il en a un. */
    public static function typeOptions(?string $current = null): array
    {
        return self::TYPES + ($current && isset(self::LEGACY_TYPES[$current]) ? [$current => self::LEGACY_TYPES[$current]] : []);
    }

    public static function typeUsesExercises(?string $type): bool
    {
        return ! in_array($type, self::WITHOUT_EXERCISES, true);
    }

    public function usesExercises(): bool
    {
        return self::typeUsesExercises($this->session_type);
    }

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

    /** Statut affiché selon l'heure : prévue, en cours (de l'heure prévue à la fin de la durée prévue, 1 h par défaut), à valider (bilan à faire)… */
    public function timeStatus(): TimeStatus
    {
        return TimeStatus::resolve($this->status, $this->scheduled_at, $this->scheduled_at->copy()->addMinutes((int) $this->planned_minutes ?: 60), true);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function typeLabel(): string
    {
        return self::allTypes()[$this->session_type] ?? $this->session_type;
    }

    public function isLesson(): bool
    {
        return in_array($this->session_type, ['private_lesson', 'group_lesson'], true);
    }

    /** Intitulé lisible : « Cours collectif avec Jimmy », « Séance dressage ». */
    public function title(): string
    {
        if ($this->isLesson()) {
            return $this->typeLabel().($this->instructor ? ' avec '.$this->instructor : '');
        }

        return 'Séance '.mb_strtolower($this->typeLabel());
    }

    public function riderLabel(): string
    {
        return $this->rider?->name ?? $this->rider_name ?? '—';
    }
}
