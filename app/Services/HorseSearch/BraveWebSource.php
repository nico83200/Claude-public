<?php

namespace App\Services\HorseSearch;

use Illuminate\Support\Facades\Http;

/**
 * Moteur de recherche web (Brave Search API, clé requise : BRAVE_SEARCH_API_KEY).
 * Ne renvoie que des pages candidates : aucune donnée structurée n'est
 * extraite des pages tierces ; l'utilisateur consulte la source et saisit.
 * https://api.search.brave.com/app/documentation/web-search
 */
class BraveWebSource implements HorseIdentitySource
{
    public function key(): string
    {
        return 'brave';
    }

    public function label(): string
    {
        return 'Recherche web';
    }

    public function reliability(): string
    {
        return 'suggested';
    }

    public function isEnabled(): bool
    {
        return filled(config('equine.horse_search.brave_api_key'));
    }

    public function search(HorseSearchQuery $query): array
    {
        $terms = array_filter(['"'.$query->name.'"', 'cheval', $query->sire, $query->ueln, $query->breed, $query->birthYear, $query->sireName, $query->damName]);
        $response = Http::timeout(config('equine.horse_search.timeout'))
            ->withHeaders(['X-Subscription-Token' => config('equine.horse_search.brave_api_key'), 'Accept' => 'application/json'])
            ->get('https://api.search.brave.com/res/v1/web/search', ['q' => implode(' ', $terms), 'count' => 10, 'search_lang' => 'fr', 'safesearch' => 'strict'])
            ->throw();

        return collect($response->json('web.results', []))->map(fn ($r, $i) => new HorseCandidate(
            id: 'brave:'.sha1($r['url'] ?? (string) $i),
            source: $this->key(),
            sourceLabel: parse_url($r['url'] ?? '', PHP_URL_HOST) ?: $this->label(),
            reliability: $this->reliability(),
            url: $r['url'] ?? null,
            fields: ['official_name' => strip_tags($r['title'] ?? '')],
            snippet: strip_tags($r['description'] ?? ''),
        ))->all();
    }
}
