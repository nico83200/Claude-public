<?php

namespace App\Models;

use App\Models\Concerns\Recurs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Recette qui revient (demi-pension chaque mois…) : chaque échéance devient une recette « à recevoir ». */
class RecurringIncome extends Model
{
    use Recurs, SoftDeletes;

    protected $guarded = ['id', 'organization_id', 'author_id'];

    protected $casts = ['amount' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'next_due_on' => 'date', 'paused_at' => 'datetime'];

    public function categoryLabel(): string
    {
        return Income::CATEGORIES[$this->category] ?? 'Recette';
    }

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function occurrences()
    {
        return $this->hasMany(Income::class);
    }
}
