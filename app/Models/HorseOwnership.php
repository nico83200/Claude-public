<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseOwnership extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_on' => 'date', 'ended_on' => 'date', 'share_percent' => 'decimal:2'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrent($q)
    {
        return $q->whereNull('ended_on')->orWhere('ended_on', '>=', now()->toDateString());
    }

    public function displayName(): string
    {
        return $this->user?->name ?? $this->owner_name ?? '—';
    }
}
