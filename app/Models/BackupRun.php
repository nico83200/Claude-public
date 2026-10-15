<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public const KINDS = ['database' => 'Base de données', 'files' => 'Photos et documents'];

    public const STATUSES = ['running' => 'En cours', 'done' => 'Envoyée', 'failed' => 'Échec'];
}
