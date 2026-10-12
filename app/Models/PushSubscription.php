<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Abonnement d'un appareil aux notifications (Web Push). */
class PushSubscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['last_success_at' => 'datetime', 'last_failure_at' => 'datetime'];

    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
