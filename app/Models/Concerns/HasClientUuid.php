<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Identifiant UUID stable, pouvant être fourni par le client.
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
