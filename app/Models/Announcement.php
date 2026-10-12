<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['plan_ids' => 'array', 'with_email' => 'boolean'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
