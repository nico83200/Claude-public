<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthObservation extends Model
{
    protected $guarded = ['id'];

    use Concerns\HasClientUuid;

    public const SEVERITIES = ['info' => 'Information', 'watch' => 'À surveiller', 'alert' => 'Alerte'];

    protected $casts = ['observed_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
