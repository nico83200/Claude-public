<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tarif d'un enseignant (fiche intervenant « Enseignant »). */
class InstructorRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'duration_minutes' => 'integer'];

    public const KINDS = ['private_lesson' => 'Cours particulier', 'group_lesson' => 'Cours collectif', 'coaching' => 'Coaching en concours', 'travel' => 'Déplacement'];

    /** Unités possibles pour chaque type de tarif. */
    public const UNITS = [
        'private_lesson' => ['session' => 'par cours', 'hour' => 'de l\'heure'],
        'group_lesson' => ['session' => 'par cavalier'],
        'coaching' => ['day' => 'par jour', 'round' => 'par parcours'],
        'travel' => ['visit' => 'par venue'],
    ];

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    /** « Cours particulier 45 min : 40,00 € par cours » */
    public function summary(): string
    {
        return ($this->label ?: self::KINDS[$this->kind] ?? $this->kind).($this->duration_minutes ? ' '.$this->duration_minutes.' min' : '')
            .' : '.number_format((float) $this->amount, 2, ',', ' ').' € '.(self::UNITS[$this->kind][$this->unit] ?? '');
    }
}
