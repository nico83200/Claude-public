<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseLocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_on' => 'date', 'ended_on' => 'date'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
