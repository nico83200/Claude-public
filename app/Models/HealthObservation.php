<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Point d'attention : ce qui a été remarqué sur le cheval, suivi jusqu'à ce que ce soit rentré dans l'ordre. */
class HealthObservation extends Model
{
    protected $guarded = ['id'];

    use Concerns\HasClientUuid;

    // Mots choisis pour ne pas affoler : on décrit l'attention à porter, pas la gravité.
    public const SEVERITIES = ['info' => 'Simple remarque', 'watch' => 'À surveiller', 'alert' => 'À voir rapidement'];

    public const TRENDS = ['better' => 'Ça va mieux', 'same' => 'Pas de changement', 'worse' => 'Ça s\'aggrave'];

    protected $casts = ['observed_at' => 'datetime', 'resolved_at' => 'datetime', 'follow_up_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function updates()
    {
        return $this->hasMany(HealthObservationUpdate::class)->orderBy('created_at')->orderBy('id');
    }

    public function photos()
    {
        return $this->hasMany(HealthObservationPhoto::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    /** Titre court : celui saisi, sinon le début de la description. */
    public function label(): string
    {
        return $this->title ?: Str::limit(strtok($this->body, "\n") ?: $this->body, 60);
    }
}
