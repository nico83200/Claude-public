<?php

namespace App\Support;

/** Limites d'envoi de l'hébergeur (php.ini) : taille des morceaux pour les envois découpés. */
class UploadLimits
{
    /** Taille de morceau sûre : au plus $maxKb, et sous upload_max_filesize / post_max_size (marge de 64 Ko). */
    public static function chunkBytes(int $maxKb = 4096): int
    {
        return max(256 * 1024, min($maxKb * 1024, self::iniBytes('upload_max_filesize') - 65536, self::iniBytes('post_max_size') - 65536));
    }

    public static function iniBytes(string $key): int
    {
        $v = trim((string) ini_get($key));
        if ($v === '' || $v === '0') {
            return PHP_INT_MAX;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n
        };
    }
}
