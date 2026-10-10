<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseOrigin extends Model
{
    protected $guarded = ['id'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }
}
