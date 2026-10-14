<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseAccessGrant extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['permissions' => 'array', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'paused_at' => 'datetime', 'revoked_at' => 'datetime'];

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

    /** Accès qui donne effectivement des droits : ni retiré, ni expiré, ni suspendu par la personne invitée, et commencé. */
    public function scopeActive($q)
    {
        return $q->current()->whereNull('paused_at')
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()));
    }

    /** Partage toujours en cours (suspendu ou à venir compris) : compte dans les limites de l'offre. */
    public function scopeCurrent($q)
    {
        return $q->whereNull('revoked_at')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return ! $this->revoked_at && ! $this->paused_at && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function isCurrent(): bool
    {
        return ! $this->revoked_at && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
