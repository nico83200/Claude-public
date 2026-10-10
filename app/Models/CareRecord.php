<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\Versioned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CareRecord extends Model
{
    use HasClientUuid, SoftDeletes, Versioned;

    protected $guarded = ['id', 'uuid', 'horse_id', 'author_id', 'version'];

    /** Valeurs par défaut (identiques à la base) disponibles dès la création. */
    protected $attributes = ['version' => 1];

    protected $casts = ['performed_at' => 'datetime', 'next_check_on' => 'date', 'cost' => 'decimal:2'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function category()
    {
        return $this->belongsTo(CareCategory::class, 'care_category_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }

    public function documents()
    {
        return $this->morphMany(HorseDocument::class, 'attachable');
    }
}
