<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOperation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'result' => 'array', 'client_created_at' => 'datetime'];
}
