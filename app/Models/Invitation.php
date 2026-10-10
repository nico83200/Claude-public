<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['permissions' => 'array', 'access_starts_at' => 'datetime', 'access_expires_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending($q)
    {
        return $q->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return ! $this->accepted_at && ! $this->revoked_at && $this->expires_at->isFuture();
    }
}
