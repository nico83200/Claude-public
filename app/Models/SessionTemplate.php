<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;

class SessionTemplate extends Model
{
    use HasClientUuid;

    protected $guarded = ['id', 'uuid', 'user_id'];

    protected $casts = ['items' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
