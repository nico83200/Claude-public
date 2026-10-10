<?php

namespace App\Services\HorseSearch;

/**
 * Résultat normalisé. Seules les valeurs réellement fournies par la source
 * sont renseignées : aucune donnée n'est déduite ou inventée.
 */
final class HorseCandidate
{
    public const FIELDS = [
        'official_name' => 'Nom', 'sex' => 'Sexe', 'birth_date' => 'Date de naissance', 'birth_year' => 'Année de naissance',
        'breed' => 'Race', 'coat' => 'Robe', 'birth_country' => 'Pays de naissance', 'breeder' => 'Éleveur',
        'sire_name' => 'Père', 'dam_name' => 'Mère', 'sire_number' => 'N° SIRE', 'ueln' => 'UELN',
    ];

    public function __construct(
        public string $id,
        public string $source,
        public string $sourceLabel,
        public string $reliability,
        public ?string $url,
        public array $fields,
        public ?string $snippet = null,
    ) {
        $this->fields = array_filter(array_intersect_key($fields, self::FIELDS), fn ($v) => $v !== null && $v !== '');
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['source'], $a['sourceLabel'], $a['reliability'], $a['url'], $a['fields'], $a['snippet'] ?? null);
    }
}
