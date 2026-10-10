<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncDevice extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['last_seen_at' => 'datetime', 'last_pull_at' => 'datetime', 'wipe_requested_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
