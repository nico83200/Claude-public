<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Identifiant UUID stable, pouvant être généré côté client (création hors ligne).
 */
trait HasClientUuid
{
    public static function bootHasClientUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
