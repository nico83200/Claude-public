<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\Versioned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Treatment extends Model
{
    use HasClientUuid, SoftDeletes, Versioned;

    protected $guarded = ['id', 'uuid', 'horse_id', 'author_id', 'version'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['version' => 1, 'status' => 'active'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'track_administrations' => 'boolean'];

    public const STATUSES = ['active' => 'En cours', 'completed' => 'Terminé', 'stopped' => 'Arrêté'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function careRecord()
    {
        return $this->belongsTo(CareRecord::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function administrations()
    {
        return $this->hasMany(TreatmentAdministration::class)->orderByDesc('administered_at');
    }

    public function scopeCurrent($q)
    {
        return $q->where('status', 'active')->where('starts_on', '<=', now()->toDateString())
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()));
    }
}
