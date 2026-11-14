<?php

namespace App\Casts;

use App\Support\PersonName;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Nom ou prénom d'une personne, toujours écrit « Prénom Nom » quelle que soit la saisie (formulaire, assistant, import, Google / Apple). */
class PersonNameCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_string($value) ? PersonName::format($value) : $value;
    }
}
