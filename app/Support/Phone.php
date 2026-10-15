<?php

namespace App\Support;

/**
 * Numéros de téléphone : format français harmonisé « 06 12 34 56 78 » (le +33 ou 0033 devient 0).
 * Un numéro étranger ou incomplet est laissé tel quel (espaces superflus retirés).
 */
class Phone
{
    public static function format(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $raw = trim(preg_replace('/\s+/', ' ', (string) $value));
        $compact = preg_replace('/[^\d+]/', '', $raw);
        if (preg_match('/^(?:\+|00)330?(\d{9})$/', $compact, $m)) {
            $compact = '0'.$m[1];
        }
        if (preg_match('/^0\d{9}$/', $compact)) {
            return implode(' ', str_split($compact, 2));
        }

        return $raw;
    }

    /** Pour les liens tel: et sms: (chiffres et + uniquement). */
    public static function dial(?string $value): ?string
    {
        return $value ? preg_replace('/[^0-9+]/', '', $value) : null;
    }
}
