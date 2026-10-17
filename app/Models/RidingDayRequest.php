<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Demande d'un jour de monte à la personne du jour (ou au propriétaire pour un jour libre), éventuellement contre un de ses jours. */
class RidingDayRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'swap_date' => 'date', 'answered_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function holder()
    {
        return $this->belongsTo(User::class, 'holder_id');
    }

    /** En attente et pas encore passée. */
    public function scopePending($q)
    {
        return $q->where('status', 'pending')->where('date', '>=', today()->toDateString());
    }
}
