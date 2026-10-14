<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Introduction IA de « Ma semaine » (une par utilisateur), valable tant que l'empreinte de la semaine ne change pas. */
class DigestIntro extends Model
{
    protected $guarded = ['id'];
}
