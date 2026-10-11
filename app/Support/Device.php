<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Détection approximative de l'appareil (affinée côté navigateur, ex. iPad « Mac tactile »). */
class Device
{
    public static function isHandheld(Request $request): bool
    {
        return (bool) preg_match('/Android|iPhone|iPad|iPod|Mobile|Tablet|Silk|Kindle|PlayBook|Opera Mini|IEMobile/i', (string) $request->userAgent());
    }
}
