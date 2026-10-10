<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorseOrganizationAssignment extends Model
{
    protected $guarded = ['id'];

    public const KINDS = ['boarding' => 'Pension', 'training' => 'Travail / dressage', 'care' => 'Soins', 'other' => 'Autre'];

    protected $casts = ['permissions' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active')->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
