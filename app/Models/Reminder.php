<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['remind_at' => 'datetime', 'sent_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function remindable()
    {
        return $this->morphTo();
    }
}
