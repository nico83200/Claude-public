<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lot de simulation de charge (super-admin) : comptes de test et tout ce qu'ils ont créé. */
class SimulationBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['params' => 'array', 'counts' => 'array', 'finished_at' => 'datetime', 'purged_at' => 'datetime'];

    public const STATUSES = ['running' => 'En cours', 'stopped' => 'Arrêtée', 'done' => 'Terminée', 'purging' => 'Suppression en cours', 'purged' => 'Supprimée'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accounts(): int
    {
        return (int) ($this->params['accounts'] ?? 0);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['running', 'stopped'], true);
    }
}
