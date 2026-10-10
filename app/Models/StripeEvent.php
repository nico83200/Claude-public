<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime', 'livemode' => 'boolean'];
}
