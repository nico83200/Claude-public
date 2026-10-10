<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseIdentifier extends Model
{
    protected $guarded = ['id'];

    public const TYPES = ['sire' => 'SIRE', 'ueln' => 'UELN', 'transponder' => 'Transpondeur', 'passport' => 'Passeport', 'fei' => 'FEI', 'other' => 'Autre'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }
}
