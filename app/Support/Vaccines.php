<?php

namespace App\Support;

use App\Models\CareRecord;
use Illuminate\Support\Str;

/** Vaccins d'une vaccination du carnet : choisis à la saisie, sinon reconnus dans le texte du soin (motif, soins réalisés). */
final class Vaccines
{
    public const TYPES = ['flu' => 'Grippe', 'rhino' => 'Rhinopneumonie', 'tetanus' => 'Tétanos', 'rabies' => 'Rage', 'strangles' => 'Gourme', 'west_nile' => 'West Nile'];

    /** Mots et noms de vaccins courants, sur le texte en minuscules et sans accents. */
    private const PATTERNS = [
        'flu' => '/grippe|influenza|\bflu\b|prequenza|proteq ?flu|duvaxyn ?ie|\bequip ?ft?\b|resequin/',
        'rhino' => '/rhino|\behv|herpes|pneumequine|duvaxyn ?ehv|resequin/',
        'tetanus' => '/tetano|tetanus|\b(prequenza|proteq ?flu|equilis) ?-?te\b|\bequip ?ft\b/',
        'rabies' => '/\brage\b|rabies|rabisin/',
        'strangles' => '/gourme|strangles/',
        'west_nile' => '/west ?nile|\bwnv\b|fievre du nil/',
    ];

    /** Vaccins reconnus dans un texte (« Rappel grippe-tétanos » : grippe et tétanos). */
    public static function infer(?string ...$texts): array
    {
        $text = Str::ascii(mb_strtolower(implode(' ', array_filter($texts))));

        return trim($text) === '' ? [] : array_keys(array_filter(self::PATTERNS, fn ($re) => preg_match($re, $text) === 1));
    }

    /** Le texte parle-t-il d'un vaccin (« Vaccin grippe », « Rappel grippe-tétanos ») et non d'une grippe soignée ? */
    public static function mentioned(?string ...$texts): bool
    {
        $text = Str::ascii(mb_strtolower(implode(' ', array_filter($texts))));

        return str_contains($text, 'vaccin') || (str_contains($text, 'rappel') && self::infer($text) !== []);
    }

    /** Vaccins d'un soin : choisis à la saisie, sinon reconnus dans son texte. */
    public static function of(CareRecord $care): array
    {
        return $care->vaccines ?: self::infer($care->reason, $care->care_performed, $care->observations);
    }

    /** « grippe et rhinopneumonie » */
    public static function names(array $keys): string
    {
        $names = collect($keys)->map(fn ($k) => mb_strtolower(self::TYPES[$k] ?? $k))->values();

        return $names->count() > 1 ? $names->slice(0, -1)->implode(', ').' et '.$names->last() : (string) $names->first();
    }
}
