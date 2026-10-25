<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Photo jointe à un point d'attention (au départ ou avec une nouvelle). Jamais servie par une adresse publique. */
class HealthObservationPhoto extends Model
{
    protected $guarded = ['id'];

    public function observation()
    {
        return $this->belongsTo(HealthObservation::class, 'health_observation_id');
    }
}
