<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Professional;

/**
 * Carnet des intervenants d'un espace : recherche d'une fiche existante (email, téléphone
 * ou nom complet) et création ou mise à jour, notamment depuis une fiche contact importée.
 */
class ProfessionalDirectory
{
    /** Fiche existante de l'espace correspondant à ces coordonnées, ou null. */
    public function match(Organization $org, array $data): ?Professional
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = self::normalizePhone($data['phone'] ?? null);
        $name = mb_strtolower(trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));

        $candidates = Professional::where('organization_id', $org->id)->get();

        return $candidates->first(fn (Professional $p) => $email !== '' && strtolower((string) $p->email) === $email)
            ?? $candidates->first(fn (Professional $p) => $phone !== null && self::normalizePhone($p->phone) === $phone)
            ?? $candidates->first(fn (Professional $p) => $name !== '' && mb_strtolower($p->fullName()) === $name && (! $email || ! $p->email) && (! $phone || ! $p->phone));
    }

    /**
     * Crée la fiche, ou met à jour celle qui correspond (champs renseignés uniquement :
     * une information absente de la fiche importée n'efface jamais l'existante).
     *
     * @return array{0: Professional, 1: bool} [fiche, créée ?]
     */
    public function upsert(Organization $org, array $data, ?Professional $existing = null): array
    {
        $existing ??= $this->match($org, $data);
        $values = array_filter($data, fn ($v) => $v !== null && $v !== '');
        if ($existing) {
            if ($existing->archived_at) {
                $values['archived_at'] = null; // de nouveau utilisée : la fiche revient dans les listes
            }
            $existing->update($values);

            return [$existing->fresh(), false];
        }
        $professional = new Professional($values);
        $professional->organization_id = $org->id;
        $professional->save();

        return [$professional, true];
    }

    /** « 06 12 34 56 78 », « +33 6 12 34 56 78 » et « 0033612345678 » donnent la même valeur. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || strlen($digits) < 6) {
            return null;
        }
        if (str_starts_with($digits, '0033')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '33') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
