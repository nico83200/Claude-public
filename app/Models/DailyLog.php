<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyLog extends Model
{
    protected $guarded = ['id'];

    use Concerns\HasClientUuid;

    public const APPETITE = ['good' => 'Bon', 'reduced' => 'Diminué', 'none' => 'Absent'];

    public const STATE = ['good' => 'Bon', 'average' => 'Moyen', 'poor' => 'Mauvais'];

    protected $casts = ['logged_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
