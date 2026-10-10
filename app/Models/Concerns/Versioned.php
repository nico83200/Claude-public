<?php

namespace App\Models\Concerns;

/**
 * Verrou optimiste : la colonne `version` est incrémentée à chaque modification.
 * Utilisée par la synchronisation pour détecter les modifications concurrentes.
 */
trait Versioned
{
    public static function bootVersioned(): void
    {
        static::updating(function ($model) {
            if ($model->isDirty() && ! $model->isDirty('version')) {
                $model->version = (int) $model->getOriginal('version') + 1;
            }
        });
    }
}
