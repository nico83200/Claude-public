<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorsePhoto extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['available_offline' => 'boolean'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }
}
