<?php

namespace App\Models;

use App\Casts\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['phone' => PhoneNumber::class, 'usual_rate' => 'decimal:2', 'archived_at' => 'datetime'];

    public const KINDS = ['veterinarian' => 'Vétérinaire', 'farrier' => 'Maréchal-ferrant', 'osteopath' => 'Ostéopathe', 'dentist' => 'Dentiste équin', 'instructor' => 'Enseignant', 'saddler' => 'Sellier', 'nutritionist' => 'Nutritionniste', 'other' => 'Autre'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function horses()
    {
        return $this->belongsToMany(Horse::class, 'horse_professionals')->withPivot('is_primary')->withTimestamps();
    }

    /** Tarifs (enseignant). */
    public function rates()
    {
        return $this->hasMany(InstructorRate::class)->orderBy('kind')->orderBy('duration_minutes');
    }

    public function careRecords()
    {
        return $this->hasMany(CareRecord::class)->orderByDesc('performed_at');
    }

    public function fullName(): string
    {
        return trim(($this->first_name ? $this->first_name.' ' : '').$this->last_name);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
