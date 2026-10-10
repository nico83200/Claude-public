<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\Versioned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Horse extends Model
{
    use HasClientUuid, HasFactory, SoftDeletes, Versioned;

    // organization_id, created_by, uuid, version : jamais issus d'une saisie utilisateur directe.
    protected $guarded = ['id', 'uuid', 'organization_id', 'created_by', 'version', 'is_demo'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['version' => 1, 'sex' => 'unknown'];

    protected $casts = [
        'birth_date' => 'date',
        'archived_at' => 'datetime',
        'is_demo' => 'boolean',
    ];

    public const SEXES = ['male' => 'Étalon / mâle', 'female' => 'Jument', 'gelding' => 'Hongre', 'unknown' => 'Non renseigné'];

    public const DISCIPLINES = ['dressage' => 'Dressage', 'jumping' => 'Saut d\'obstacles', 'eventing' => 'Concours complet', 'endurance' => 'Endurance', 'western' => 'Western', 'trail' => 'Randonnée / extérieur', 'driving' => 'Attelage', 'vaulting' => 'Voltige', 'horseball' => 'Horse-ball', 'leisure' => 'Loisir', 'groundwork' => 'Travail à pied', 'other' => 'Autre'];

    /** Champs modifiables hors ligne (liste blanche explicite). */
    public const OFFLINE_EDITABLE = ['usual_name', 'particularities', 'general_notes', 'current_location'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function breed()
    {
        return $this->belongsTo(HorseBreed::class, 'horse_breed_id');
    }

    public function identifiers()
    {
        return $this->hasMany(HorseIdentifier::class);
    }

    public function origin()
    {
        return $this->hasOne(HorseOrigin::class);
    }

    public function pedigree()
    {
        return $this->hasMany(HorseRelationship::class);
    }

    public function photos()
    {
        return $this->hasMany(HorsePhoto::class)->latest();
    }

    public function documents()
    {
        return $this->hasMany(HorseDocument::class)->latest();
    }

    public function externalSources()
    {
        return $this->hasMany(HorseExternalSource::class)->latest('fetched_at');
    }

    public function locations()
    {
        return $this->hasMany(HorseLocation::class)->orderByDesc('started_on');
    }

    public function ownerships()
    {
        return $this->hasMany(HorseOwnership::class);
    }

    public function assignments()
    {
        return $this->hasMany(HorseOrganizationAssignment::class);
    }

    public function accessGrants()
    {
        return $this->hasMany(HorseAccessGrant::class);
    }

    public function professionals()
    {
        return $this->belongsToMany(Professional::class, 'horse_professionals')->withPivot('is_primary')->withTimestamps();
    }

    public function careRecords()
    {
        return $this->hasMany(CareRecord::class)->orderByDesc('performed_at');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class)->orderByDesc('starts_on');
    }

    public function observations()
    {
        return $this->hasMany(HealthObservation::class)->orderByDesc('observed_at');
    }

    public function sessions()
    {
        return $this->hasMany(RidingSession::class)->orderByDesc('scheduled_at');
    }

    public function events()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function feedingPlans()
    {
        return $this->hasMany(FeedingPlan::class)->orderByDesc('starts_on');
    }

    public function dailyLogs()
    {
        return $this->hasMany(DailyLog::class)->orderByDesc('logged_at');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class)->orderByDesc('spent_on');
    }

    // Alias utilisés par les liaisons de routes imbriquées (scopeBindings).
    public function cares()
    {
        return $this->hasMany(CareRecord::class);
    }

    public function sources()
    {
        return $this->hasMany(HorseExternalSource::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function grants()
    {
        return $this->hasMany(HorseAccessGrant::class);
    }

    public function identifier(string $type): ?string
    {
        return $this->identifiers->firstWhere('type', $type)?->value;
    }

    public function displayName(): string
    {
        return $this->usual_name ? "{$this->usual_name} ({$this->official_name})" : $this->official_name;
    }

    public function shortName(): string
    {
        return $this->usual_name ?: $this->official_name;
    }

    public function age(): ?int
    {
        if ($this->birth_date) {
            return $this->birth_date->age;
        }

        return $this->birth_year ? now()->year - $this->birth_year : null;
    }

    /** main_photo_path contient l'identifiant de la photo principale (servie par contrôleur). */
    public function mainPhotoUrl(bool $thumb = true): ?string
    {
        return $this->main_photo_path ? route('horses.photos.show', [$this->id, $this->main_photo_path]).($thumb ? '?thumb=1' : '') : null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
