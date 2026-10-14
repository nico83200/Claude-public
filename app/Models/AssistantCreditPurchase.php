<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Achat de crédits IA (Stripe Checkout, paiement unique). */
class AssistantCreditPurchase extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid_at' => 'datetime'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
