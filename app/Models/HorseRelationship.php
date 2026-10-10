<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseRelationship extends Model
{
    protected $guarded = ['id'];

    public const RELATIONS = ['sire' => 'Père', 'dam' => 'Mère', 'sire_sire' => 'Grand-père paternel', 'sire_dam' => 'Grand-mère paternelle', 'dam_sire' => 'Grand-père maternel', 'dam_dam' => 'Grand-mère maternelle'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function related()
    {
        return $this->belongsTo(Horse::class, 'related_horse_id');
    }
}
