<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une nouvelle donnée dans un point d'attention : évolution, ce qui a été fait, photos. */
class HealthObservationUpdate extends Model
{
    protected $guarded = ['id'];

    public function observation()
    {
        return $this->belongsTo(HealthObservation::class, 'health_observation_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function photos()
    {
        return $this->hasMany(HealthObservationPhoto::class)->orderBy('id');
    }
}
