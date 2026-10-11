<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppUpdate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    protected $hidden = ['recovery_secret'];

    public const STATUSES = [
        'uploaded' => 'Envoyée', 'verified' => 'Prête à installer', 'rejected' => 'Refusée', 'applying' => 'Installation en cours', 'migrating' => 'Mise à jour de la base',
        'done' => 'Installée', 'failed' => 'Échec (version précédente restaurée)', 'restored' => 'Restaurée',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appendLog(string $line): void
    {
        $this->log = ltrim(($this->log ?? '')."\n".now()->format('H:i:s').' '.$line);
        $this->save();
    }
}
