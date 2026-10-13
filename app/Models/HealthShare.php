<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthShare extends Model
{
    protected $guarded = ['id', 'token'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_viewed_at' => 'datetime'];

    public const DURATIONS = [1 => '24 heures', 7 => '7 jours', 30 => '30 jours'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }

    public function scopeActive($q)
    {
        return $q->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
