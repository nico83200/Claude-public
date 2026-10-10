<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\Horse;
use App\Models\HorseBreed;
use App\Models\HorseExternalSource;
use App\Models\HorseRelationship;
use App\Services\Audit;
use App\Services\HorseSearch\HorseCandidate;
use App\Services\HorseSearch\HorseSearchQuery;
use App\Services\HorseSearch\HorseSearchService;
use App\Services\HorseService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * « Rechercher mon cheval » : recherche → choix explicite → validation des
 * champs à importer → enregistrement avec provenance. Aucun choix automatique.
 */
class HorseSearchController extends Controller
{
    public function form(Request $request, HorseSearchService $service)
    {
        abort_unless(FeatureFlag::enabled('horse_search'), 404);

        return view('horse-search.form', [
            'sources' => array_map(fn ($s) => $s->label(), $service->sources()),
            'targetHorse' => $request->integer('horse') ? Horse::find($request->integer('horse')) : null,
            'included' => $this->entitlements()->hasFeature($this->currentOrganization(), 'horse_search'),
        ]);
    }

    public function search(Request $request, HorseSearchService $service)
    {
        abort_unless(FeatureFlag::enabled('horse_search'), 404);
        $this->requireFeature($this->currentOrganization(), 'horse_search', 'Recherche d\'identité');
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'sire' => ['nullable', 'string', 'max:20'],
            'ueln' => ['nullable', 'string', 'max:20'],
            'breed' => ['nullable', 'string', 'max:60'],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'sire_name' => ['nullable', 'string', 'max:100'],
            'dam_name' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:60'],
            'horse_id' => ['nullable', 'integer'],
        ]);
        $result = $service->search(HorseSearchQuery::fromArray($data));
        $token = Str::random(32);
        Cache::put($this->cacheKey($request, $token), array_map(fn ($c) => $c->toArray(), $result['candidates']), now()->addMinutes(30));

        return view('horse-search.results', [
            'query' => $data, 'token' => $token, 'candidates' => $result['candidates'], 'sourceErrors' => $result['errors'], 'sources' => $result['sources'],
            'differing' => $this->differingFields($result['candidates']),
            'targetHorse' => ! empty($data['horse_id']) ? Horse::find($data['horse_id']) : null,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'candidate' => ['required', 'string'], 'horse_id' => ['nullable', 'integer']]);
        $candidate = $this->candidate($request, $data['token'], $data['candidate']);
        $horse = ! empty($data['horse_id']) ? Horse::findOrFail($data['horse_id']) : null;
        if ($horse) {
            $this->authorizeHorse($horse, Perm::HORSE_EDIT);
        } else {
            $this->authorizeOrg($this->currentOrganization(), Perm::HORSES_CREATE);
        }

        return view('horse-search.preview', ['candidate' => $candidate, 'token' => $data['token'], 'horse' => $horse?->load(['identifiers', 'breed', 'pedigree'])]);
    }

    public function import(Request $request, HorseService $horses)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], 'candidate' => ['required', 'string'], 'horse_id' => ['nullable', 'integer'],
            'fields' => ['required', 'array', 'min:1'], 'fields.*' => ['string'],
        ], ['fields.required' => 'Sélectionnez au moins une information à importer.']);
        $candidate = $this->candidate($request, $data['token'], $data['candidate']);
        $selected = array_intersect_key($candidate->fields, array_flip($data['fields']));

        $horse = DB::transaction(function () use ($request, $data, $selected, $candidate, $horses) {
            $attributes = $this->mapToHorse($selected);
            if (! empty($data['horse_id'])) {
                $horse = Horse::findOrFail($data['horse_id']);
                $this->authorizeHorse($horse, Perm::HORSE_EDIT);
                $horses->update($horse, $attributes);
            } else {
                $org = $this->currentOrganization();
                $this->authorizeOrg($org, Perm::HORSES_CREATE);
                $attributes['official_name'] ??= $request->input('fallback_name', 'Cheval importé');
                $attributes['sex'] ??= 'unknown';
                $horse = $horses->create($org, $request->user(), $attributes, $org->isPersonal());
            }
            foreach (['sire_name' => 'sire', 'dam_name' => 'dam'] as $field => $relation) {
                if (! empty($selected[$field])) {
                    HorseRelationship::updateOrCreate(['horse_id' => $horse->id, 'relation' => $relation], ['related_name' => $selected[$field], 'external_reference' => $candidate->url]);
                }
            }
            foreach ($selected as $field => $value) {
                HorseExternalSource::create([
                    'horse_id' => $horse->id, 'source' => $candidate->sourceLabel, 'url' => $candidate->url, 'field' => $field,
                    'value' => (string) $value, 'fetched_at' => now(), 'reliability' => $candidate->reliability,
                    'validation' => 'confirmed', 'validated_by' => $request->user()->id,
                ]);
            }
            Audit::log('horse.imported', $horse, ['source' => $candidate->source, 'url' => $candidate->url, 'fields' => array_keys($selected)], $horse->organization_id);

            return $horse;
        });

        return redirect()->route('horses.show', $horse)->with('success', 'Informations importées avec leur provenance. Vous pouvez les compléter ou les corriger à tout moment.');
    }

    private function mapToHorse(array $f): array
    {
        $out = [];
        if (isset($f['official_name'])) {
            $out['official_name'] = mb_substr($f['official_name'], 0, 150);
        }
        if (isset($f['sex']) && array_key_exists($f['sex'], Horse::SEXES)) {
            $out['sex'] = $f['sex'];
        }
        if (isset($f['birth_date'])) {
            $out['birth_date'] = $f['birth_date'];
        }
        if (isset($f['birth_year'])) {
            $out['birth_year'] = (int) $f['birth_year'];
        }
        if (isset($f['coat'])) {
            $out['coat'] = $f['coat'];
        }
        if (isset($f['breeder'])) {
            $out['breeder'] = $f['breeder'];
        }
        if (isset($f['birth_country']) && strlen($f['birth_country']) === 2) {
            $out['birth_country'] = strtoupper($f['birth_country']);
        }
        if (isset($f['breed'])) {
            $out['horse_breed_id'] = HorseBreed::firstOrCreate(['name' => mb_substr($f['breed'], 0, 100)])->id;
        }
        $ids = array_filter(['sire' => $f['sire_number'] ?? null, 'ueln' => $f['ueln'] ?? null]);
        if ($ids) {
            $out['identifiers'] = $ids;
        }

        return $out;
    }

    private function differingFields(array $candidates): array
    {
        $values = [];
        foreach ($candidates as $c) {
            foreach ($c->fields as $k => $v) {
                $values[$k][] = mb_strtolower((string) $v);
            }
        }

        return array_keys(array_filter($values, fn ($v) => count(array_unique($v)) > 1));
    }

    private function candidate(Request $request, string $token, string $id): HorseCandidate
    {
        $list = Cache::get($this->cacheKey($request, $token));
        abort_unless(is_array($list), 410, 'Résultats expirés : relancez la recherche.');
        $found = collect($list)->firstWhere('id', $id);
        abort_unless($found, 404);

        return HorseCandidate::fromArray($found);
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'horse-search-results:'.$request->user()->id.':'.$token;
    }
}
