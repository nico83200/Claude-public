<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseAccessGrant extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['permissions' => 'array', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantor()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function scopeActive($q)
    {
        return $q->whereNull('revoked_at')
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return ! $this->revoked_at && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
