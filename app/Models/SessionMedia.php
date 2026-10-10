<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionMedia extends Model
{
    protected $guarded = ['id'];

    protected $table = 'session_media';

    public function session()
    {
        return $this->belongsTo(RidingSession::class, 'riding_session_id');
    }
}
