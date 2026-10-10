<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPriceChange extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['migration_effective_on' => 'date', 'subscribers_notified_at' => 'datetime'];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
