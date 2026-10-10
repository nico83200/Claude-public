<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionComment extends Model
{
    protected $guarded = ['id'];

    use Concerns\HasClientUuid;

    public function session()
    {
        return $this->belongsTo(RidingSession::class, 'riding_session_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
