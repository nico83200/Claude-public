<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Prestation ponctuelle facturable au propriétaire (refacturée sur le relevé du mois). */
class BoardingCharge extends Model
{
    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['charged_on' => 'date', 'quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function total(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
