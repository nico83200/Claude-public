<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2', 'amount_refunded' => 'decimal:2', 'period_start' => 'datetime', 'period_end' => 'datetime', 'paid_at' => 'datetime'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
