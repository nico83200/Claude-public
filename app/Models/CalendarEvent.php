<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasClientUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'organization_id', 'created_by'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['status' => 'planned', 'duration_minutes' => 60, 'recurrence_interval' => 1];

    protected $casts = ['starts_at' => 'datetime', 'all_day' => 'boolean', 'recurrence_until' => 'date'];

    public const TYPES = [
        'veterinary' => 'Vétérinaire', 'farrier' => 'Maréchalerie', 'care' => 'Soin', 'treatment' => 'Traitement',
        'session' => 'Séance', 'lesson' => 'Cours', 'competition' => 'Concours', 'ride' => 'Balade',
        'rest' => 'Repos', 'custom' => 'Autre',
    ];

    public const STATUSES = ['planned' => 'Prévu', 'confirmed' => 'Confirmé', 'done' => 'Réalisé', 'cancelled' => 'Annulé'];

    public const COLORS = ['veterinary' => 'red', 'farrier' => 'amber', 'care' => 'rose', 'treatment' => 'purple', 'session' => 'emerald', 'lesson' => 'teal', 'competition' => 'blue', 'ride' => 'lime', 'rest' => 'slate', 'custom' => 'sky'];

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
}
