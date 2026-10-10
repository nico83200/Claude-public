<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function entries()
    {
        return $this->hasMany(FeedingEntry::class)->orderBy('time_of_day');
    }
}
