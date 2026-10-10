<?php

namespace App\Services\HorseSearch;

final class HorseSearchQuery
{
    public function __construct(
        public string $name,
        public ?string $sire = null,
        public ?string $ueln = null,
        public ?string $breed = null,
        public ?int $birthYear = null,
        public ?string $sireName = null,
        public ?string $damName = null,
        public ?string $country = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(trim($a['name']), $a['sire'] ?? null, $a['ueln'] ?? null, $a['breed'] ?? null,
            isset($a['birth_year']) ? (int) $a['birth_year'] : null, $a['sire_name'] ?? null, $a['dam_name'] ?? null, $a['country'] ?? null);
    }

    public function cacheKey(): string
    {
        return sha1(json_encode(get_object_vars($this)));
    }
}
