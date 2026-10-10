<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeCustomer extends Model
{
    protected $guarded = ['id'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
