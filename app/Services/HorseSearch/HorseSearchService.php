<?php

namespace App\Services\HorseSearch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class HorseSearchService
{
    /** @return HorseIdentitySource[] */
    public function sources(): array
    {
        return array_values(array_filter([
            app(WikidataSource::class),
            app(BraveWebSource::class),
        ], fn (HorseIdentitySource $s) => $s->isEnabled()));
    }

    /**
     * @return array{candidates: HorseCandidate[], errors: array<string, string>, sources: string[]}
     */
    public function search(HorseSearchQuery $query): array
    {
        $candidates = [];
        $errors = [];
        foreach ($this->sources() as $source) {
            try {
                $found = Cache::remember('horse-search:'.$source->key().':'.$query->cacheKey(), now()->addMinutes(config('equine.horse_search.cache_minutes')),
                    fn () => array_map(fn (HorseCandidate $c) => $c->toArray(), $source->search($query)));
                foreach ($found as $c) {
                    $candidates[] = HorseCandidate::fromArray($c);
                }
            } catch (Throwable $e) {
                Log::warning('Recherche cheval : échec de la source '.$source->key(), ['error' => $e->getMessage()]);
                $errors[$source->label()] = 'Source momentanément indisponible.';
            }
        }

        return ['candidates' => $this->rank($candidates, $query), 'errors' => $errors, 'sources' => array_map(fn ($s) => $s->label(), $this->sources())];
    }

    /** Classe les résultats selon leur concordance avec les critères (sans en exclure). */
    private function rank(array $candidates, HorseSearchQuery $q): array
    {
        $score = function (HorseCandidate $c) use ($q) {
            $s = ['official' => 30, 'reference' => 20, 'suggested' => 0][$c->reliability] ?? 0;
            $f = $c->fields;
            if (isset($f['official_name']) && mb_strtolower($f['official_name']) === mb_strtolower($q->name)) {
                $s += 20;
            }
            if ($q->birthYear && ($f['birth_year'] ?? null) == $q->birthYear) {
                $s += 15;
            }
            foreach (['sireName' => 'sire_name', 'damName' => 'dam_name', 'breed' => 'breed'] as $qk => $fk) {
                if ($q->$qk && isset($f[$fk]) && str_contains(mb_strtolower($f[$fk]), mb_strtolower($q->$qk))) {
                    $s += 10;
                }
            }
            if ($q->sire && ($f['sire_number'] ?? null) === strtoupper($q->sire)) {
                $s += 50;
            }

            return $s;
        };
        usort($candidates, fn ($a, $b) => $score($b) <=> $score($a));

        return $candidates;
    }
}
