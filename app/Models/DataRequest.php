<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['handled_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
