<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseExternalSource extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['fetched_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }
}
