<?php

namespace App\Services\HorseSearch;

/**
 * Adaptateur de source externe. Pour ajouter une source : implémenter cette
 * interface et l'enregistrer dans HorseSearchService::sources().
 */
interface HorseIdentitySource
{
    public function key(): string;

    public function label(): string;

    /** official | reference | suggested */
    public function reliability(): string;

    public function isEnabled(): bool;

    /** @return HorseCandidate[] */
    public function search(HorseSearchQuery $query): array;
}
