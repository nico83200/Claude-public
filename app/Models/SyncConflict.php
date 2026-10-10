<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncConflict extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['local_values' => 'array', 'server_values' => 'array', 'resolved_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
