<?php

namespace App\Casts;

use App\Support\Phone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Numéro de téléphone français affiché et enregistré au format « 06 12 34 56 78 ». */
class PhoneNumber implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return Phone::format($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return Phone::format($value);
    }
}
