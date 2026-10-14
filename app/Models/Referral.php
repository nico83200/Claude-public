<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Filleul inscrit grâce au lien d'un parrain, et jours offerts au parrain. */
class Referral extends Model
{
    public const STATUSES = [
        'pending' => 'Inscription à confirmer',
        'rewarded' => 'Jours offerts',
        'banked' => 'Jours en réserve',
        'used' => 'Jours utilisés',
        'capped' => 'Plafond annuel atteint',
    ];

    protected $guarded = ['id'];

    protected $casts = ['rewarded_at' => 'datetime'];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee()
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
