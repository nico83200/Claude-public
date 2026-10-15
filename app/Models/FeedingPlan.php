<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une ration (« Ration du matin », « Complément articulations 15 jours »…). Plusieurs peuvent être suivies
 * en même temps ; une ration terminée (date de fin passée) ou archivée reste consultable dans l'historique.
 */
class FeedingPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'archived_at' => 'datetime'];

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

    /** Rations suivies (non archivées, pas encore terminées), y compris celles qui commencent plus tard. */
    public function scopeOngoing($q)
    {
        return $q->whereNull('archived_at')->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()));
    }

    /** Rations en cours aujourd'hui. */
    public function scopeActiveToday($q)
    {
        return $q->ongoing()->where('starts_on', '<=', now()->toDateString());
    }

    public function isUpcoming(): bool
    {
        return $this->starts_on->isFuture();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null || ($this->ends_on && $this->ends_on->lt(today()));
    }
}
