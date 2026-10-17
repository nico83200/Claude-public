<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Petites règles d'écriture en français pour l'affichage. */
class Fr
{
    /** « 1 cheval », « 3 chevaux », « 0 membre » (le singulier vaut pour 0 et 1). */
    public static function count(int|float|null $n, string $singular, string $plural): string
    {
        $n = (int) $n;

        return $n.' '.(abs($n) >= 2 ? $plural : $singular);
    }

    /** « 1er novembre 2026 », « 15 novembre » (sans l'année). */
    public static function day(\DateTimeInterface $date, bool $year = true): string
    {
        $d = Carbon::instance($date);

        return ($d->day === 1 ? '1er' : $d->day).' '.$d->translatedFormat('F').($year ? ' '.$d->year : '');
    }

    /** Taille en mégaoctets lisible : « 500 Mo », « 10 Go », « 1,5 Go ». */
    public static function megabytes(int|float|null $mb): string
    {
        $mb = (float) $mb;
        if ($mb >= 1024) {
            $go = $mb / 1024;

            return rtrim(rtrim(number_format($go, 1, ',', ' '), '0'), ',').' Go';
        }

        return number_format($mb, 0, ',', ' ').' Mo';
    }
}
