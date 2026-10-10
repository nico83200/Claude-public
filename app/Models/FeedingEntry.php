<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_supplement' => 'boolean', 'quantity' => 'decimal:2'];

    public function plan()
    {
        return $this->belongsTo(FeedingPlan::class, 'feeding_plan_id');
    }
}
