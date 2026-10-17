<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jour de monte habituel d'un cheval : chaque jour de la semaine revient à une personne (propriétaire, demi-pension…). */
class RidingDay extends Model
{
    protected $table = 'horse_riding_days';

    protected $guarded = ['id'];

    public const WEEKDAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
