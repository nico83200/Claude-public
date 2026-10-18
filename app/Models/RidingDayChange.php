<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Échange ponctuel d'un jour de monte (« je te laisse samedi ») : la personne qui monte ce jour-là, ou personne (jour libre). */
class RidingDayChange extends Model
{
    protected $table = 'horse_riding_day_changes';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'is_rest' => 'boolean'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
