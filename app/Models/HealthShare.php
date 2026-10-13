<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthShare extends Model
{
    protected $guarded = ['id', 'token'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_viewed_at' => 'datetime', 'emailed_at' => 'datetime'];

    /** Durée pendant laquelle le lien reste ouvert (jours). */
    public const DURATIONS = [1 => '24 heures', 3 => '3 jours', 7 => '7 jours', 14 => '15 jours', 30 => '1 mois', 90 => '3 mois'];

    public function url(): string
    {
        return route('vet-summary.public', $this->token);
    }

    /** Texte d'envoi (SMS ou message) avec le lien direct. */
    public function message(string $from): string
    {
        return 'Bonjour, voici le résumé de santé de '.$this->horse->shortName().' (6 derniers mois), consultable sans compte jusqu\'au '
            .$this->expires_at->translatedFormat('j F Y').' : '.$this->url().' — '.$from;
    }

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
