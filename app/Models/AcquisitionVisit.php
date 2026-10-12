<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcquisitionVisit extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['registered_at' => 'datetime', 'code_redeemed_at' => 'datetime'];
}
