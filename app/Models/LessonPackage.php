<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** Carte de cours (N cours payés d'avance) ou forfait mensuel (N cours par mois) avec un enseignant, pour un cheval. */
class LessonPackage extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['price' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'lessons' => 'integer'];

    public const KINDS = ['card' => 'Carte de cours', 'monthly' => 'Forfait mensuel'];

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function charges()
    {
        return $this->hasMany(LessonCharge::class, 'package_id');
    }

    /** Valable ce jour-là (carte : jusqu'à sa date de fin ; forfait : chaque mois de sa période). */
    public function covers(Carbon $day): bool
    {
        return ! $this->trashed() && $day->gte($this->starts_on->copy()->startOfDay()) && (! $this->ends_on || $day->lte($this->ends_on->copy()->endOfDay()));
    }

    /** « Carte de 10 cours avec Jimmy BLANC », « Forfait de 4 cours par mois avec Jimmy BLANC » */
    public function title(): string
    {
        return ($this->kind === 'card' ? 'Carte de '.$this->lessons.' cours' : 'Forfait de '.$this->lessons.' cours par mois').' avec '.($this->professional?->fullName() ?? 'l\'enseignant');
    }
}
