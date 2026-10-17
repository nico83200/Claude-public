<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Services\CalendarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CalendarEvent extends Model
{
    use HasClientUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'organization_id', 'created_by', 'care_record_id', 'treatment_id'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['status' => 'planned', 'duration_minutes' => 60, 'recurrence_interval' => 1];

    protected $casts = ['starts_at' => 'datetime', 'all_day' => 'boolean', 'recurrence_until' => 'date', 'recurrence_exceptions' => 'array'];

    public const TYPES = [
        'veterinary' => 'Vétérinaire', 'farrier' => 'Maréchalerie', 'care' => 'Soin', 'treatment' => 'Traitement',
        'session' => 'Séance', 'lesson' => 'Cours', 'competition' => 'Concours', 'ride' => 'Balade',
        'rest' => 'Repos', 'custom' => 'Autre',
    ];

    public const STATUSES = ['planned' => 'Prévu', 'confirmed' => 'Confirmé', 'done' => 'Réalisé', 'cancelled' => 'Annulé'];

    public const COLORS = ['veterinary' => 'red', 'farrier' => 'amber', 'care' => 'rose', 'treatment' => 'purple', 'session' => 'emerald', 'lesson' => 'teal', 'competition' => 'blue', 'ride' => 'lime', 'rest' => 'slate', 'custom' => 'sky'];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function endsAt()
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function isSessionEvent(): bool
    {
        return $this->source_type === (new RidingSession)->getMorphClass();
    }

    /** Date retirée de la série (occurrence supprimée, ou validée et devenue un événement à part). */
    public function isExcluded(string $date): bool
    {
        return in_array($date, $this->recurrence_exceptions ?? [], true);
    }

    public function exclude(string $date): void
    {
        $this->recurrence_exceptions = collect($this->recurrence_exceptions ?? [])->push($date)->unique()->sort()->values()->all();
    }

    public function reinclude(string $date): void
    {
        $this->recurrence_exceptions = collect($this->recurrence_exceptions ?? [])->reject(fn ($d) => $d === $date)->values()->all() ?: null;
    }

    /** Occurrences de la série entre deux dates (exceptions retirées). */
    public function occurrencesBetween(Carbon $from, Carbon $to): Collection
    {
        return app(CalendarService::class)->expand(collect([$this]), $from, $to)->pluck('at');
    }

    /** La date est-elle une occurrence de la série ? */
    public function occursOn(string $date): bool
    {
        $day = Carbon::parse($date);

        return $this->recurrence && $this->occurrencesBetween($day->copy()->startOfDay(), $day->copy()->endOfDay())->isNotEmpty();
    }

    /** Autres événements du même rendez-vous groupé (un passage pour plusieurs chevaux). */
    public function groupSiblings()
    {
        return $this->group_uuid
            ? static::with('horse')->where('group_uuid', $this->group_uuid)->whereKeyNot($this->id)->orderBy('id')->get()
            : collect();
    }

    public function scopeVisibleSessions($query, iterable $sessionHorseIds)
    {
        $morph = (new RidingSession)->getMorphClass();
        $ids = collect($sessionHorseIds)->all();

        return $query->where(fn ($q) => $q->whereNull('source_type')->orWhere('source_type', '!=', $morph)->orWhereIn('horse_id', $ids ?: [0]));
    }

    /** Soin du carnet de santé correspondant (synchronisation Agenda ↔ Santé). */
    public function careRecord()
    {
        return $this->belongsTo(CareRecord::class);
    }
}
