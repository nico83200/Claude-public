<?php

namespace App\Services\HorseSearch;

use Illuminate\Support\Facades\Http;

/**
 * Wikidata (données CC0, API publique sans clé). Couvre essentiellement les
 * chevaux notables (sport, courses, étalons connus).
 * https://www.wikidata.org/wiki/Wikidata:Data_access
 */
class WikidataSource implements HorseIdentitySource
{
    private const API = 'https://www.wikidata.org/w/api.php';

    private const HORSE = 'Q726';

    public function key(): string
    {
        return 'wikidata';
    }

    public function label(): string
    {
        return 'Wikidata';
    }

    public function reliability(): string
    {
        return 'reference';
    }

    public function isEnabled(): bool
    {
        return (bool) config('equine.horse_search.wikidata');
    }

    public function search(HorseSearchQuery $query): array
    {
        $http = Http::timeout(config('equine.horse_search.timeout'))->withUserAgent(config('equine.horse_search.user_agent'))->acceptJson();

        $found = $http->get(self::API, ['action' => 'wbsearchentities', 'search' => $query->name, 'language' => 'fr', 'uselang' => 'fr', 'type' => 'item', 'limit' => 20, 'format' => 'json'])->throw()->json('search', []);
        $ids = collect($found)->pluck('id')->filter()->take(20)->implode('|');
        if ($ids === '') {
            return [];
        }

        $entities = $http->get(self::API, ['action' => 'wbgetentities', 'ids' => $ids, 'props' => 'labels|claims', 'languages' => 'fr|en', 'format' => 'json'])->throw()->json('entities', []);
        $horses = collect($entities)->filter(fn ($e) => in_array(self::HORSE, $this->itemValues($e, 'P31'), true));
        if ($horses->isEmpty()) {
            return [];
        }

        // Libellés des entités liées (père, mère, race, pays, sexe).
        $refs = $horses->flatMap(fn ($e) => [...$this->itemValues($e, 'P22'), ...$this->itemValues($e, 'P25'), ...$this->itemValues($e, 'P4743'), ...$this->itemValues($e, 'P495'), ...$this->itemValues($e, 'P21')])->unique()->take(50);
        $labels = [];
        if ($refs->isNotEmpty()) {
            $labelled = $http->get(self::API, ['action' => 'wbgetentities', 'ids' => $refs->implode('|'), 'props' => 'labels|claims', 'languages' => 'fr|en', 'format' => 'json'])->throw()->json('entities', []);
            foreach ($labelled as $id => $e) {
                $labels[$id] = $e['labels']['fr']['value'] ?? $e['labels']['en']['value'] ?? null;
                $iso = $e['claims']['P297'][0]['mainsnak']['datavalue']['value'] ?? null; // code ISO du pays
                if ($iso) {
                    $labels[$id.':iso'] = $iso;
                }
            }
        }

        $out = [];
        foreach ($horses as $id => $e) {
            $birth = $e['claims']['P569'][0]['mainsnak']['datavalue']['value']['time'] ?? null;
            $precision = $e['claims']['P569'][0]['mainsnak']['datavalue']['value']['precision'] ?? 0;
            $year = $birth ? (int) substr($birth, 1, 4) : null;
            $sexId = $this->itemValues($e, 'P21')[0] ?? null;
            $country = $this->itemValues($e, 'P495')[0] ?? null;
            $out[] = new HorseCandidate(
                id: 'wikidata:'.$id,
                source: $this->key(),
                sourceLabel: $this->label(),
                reliability: $this->reliability(),
                url: 'https://www.wikidata.org/wiki/'.$id,
                fields: [
                    'official_name' => $e['labels']['fr']['value'] ?? $e['labels']['en']['value'] ?? null,
                    'birth_year' => $year,
                    'birth_date' => $birth && $precision >= 11 ? substr($birth, 1, 10) : null,
                    'sex' => match ($sexId) {
                        'Q44148' => 'male', 'Q43445' => 'female', default => null
                    },
                    'sire_name' => ($p = $this->itemValues($e, 'P22')[0] ?? null) ? ($labels[$p] ?? null) : null,
                    'dam_name' => ($m = $this->itemValues($e, 'P25')[0] ?? null) ? ($labels[$m] ?? null) : null,
                    'breed' => ($b = $this->itemValues($e, 'P4743')[0] ?? null) ? ($labels[$b] ?? null) : null,
                    'birth_country' => $country ? ($labels[$country.':iso'] ?? null) : null,
                ],
            );
        }

        return $out;
    }

    private function itemValues(array $entity, string $property): array
    {
        return collect($entity['claims'][$property] ?? [])->map(fn ($c) => $c['mainsnak']['datavalue']['value']['id'] ?? null)->filter()->values()->all();
    }
}
