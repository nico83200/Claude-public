<?php

namespace App\Support;

/**
 * Écriture des noms et prénoms partout dans l'appli : première lettre en majuscule, la suite en minuscules,
 * pour chaque partie d'un nom composé (« jean-PIERRE dupont » → « Jean-Pierre Dupont », « o'neil » → « O'Neil »).
 * Particules en minuscules après le premier mot (« Charles de Gaulle », « Anne d'Artagnan ») ;
 * une mention entre parenthèses reste en minuscules (« Claire Dupont (démo) »).
 */
class PersonName
{
    private const PARTICLES = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'van', 'von', 'der', 'den', 'di', 'da', 'del', 'dos'];

    public static function format(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            return $name;
        }
        $words = explode(' ', mb_strtolower($name));
        foreach ($words as $i => $word) {
            if (str_starts_with($word, '(')) {
                continue;
            }
            if ($i > 0 && in_array($word, self::PARTICLES, true)) {
                continue;
            }
            // « d'artagnan » après le premier mot : particule en minuscule, nom en majuscule.
            if ($i > 0 && preg_match("/^([dl])(['’])(.+)$/u", $word, $m)) {
                $words[$i] = $m[1].$m[2].self::capitalizeParts($m[3]);

                continue;
            }
            $words[$i] = self::capitalizeParts($word);
        }

        return implode(' ', $words);
    }

    /** Majuscule au début de chaque partie : après un trait d'union, une apostrophe ou un point (« j.-p. »). */
    private static function capitalizeParts(string $word): string
    {
        return preg_replace_callback("/(^|[-'’.])(\p{L})/u", fn ($m) => $m[1].mb_strtoupper($m[2]), $word);
    }
}
