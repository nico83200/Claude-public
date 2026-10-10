@php $fields = \App\Services\HorseSearch\HorseCandidate::FIELDS; $sexes = \App\Models\Horse::SEXES; @endphp
<x-layouts.app title="Valider l'import">
    <x-page-header title="Valider l'importation" :subtitle="'Source : '.$candidate->sourceLabel" />
    <form method="POST" action="{{ route('horse-search.import') }}" class="max-w-2xl space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="candidate" value="{{ $candidate->id }}">
        @if ($horse)<input type="hidden" name="horse_id" value="{{ $horse->id }}">@endif
        <x-section :title="$horse ? 'Informations qui compléteront la fiche de '.$horse->shortName() : 'Informations qui seront enregistrées dans une nouvelle fiche'">
            <div class="table-wrap"><table class="table">
                <thead><tr><th></th><th>Champ</th><th>Valeur trouvée</th>@if ($horse)<th>Valeur actuelle</th>@endif</tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($candidate->fields as $k => $v)
                    @php
                        $current = $horse ? match ($k) { 'official_name' => $horse->official_name, 'sex' => $sexes[$horse->sex] ?? null, 'birth_date' => $horse->birth_date?->format('Y-m-d'), 'birth_year' => $horse->birth_year, 'breed' => $horse->breed?->name, 'coat' => $horse->coat, 'birth_country' => $horse->birth_country, 'breeder' => $horse->breeder, 'sire_name' => $horse->pedigree->firstWhere('relation', 'sire')?->related_name, 'dam_name' => $horse->pedigree->firstWhere('relation', 'dam')?->related_name, 'sire_number' => $horse->identifier('sire'), 'ueln' => $horse->identifier('ueln'), default => null } : null;
                    @endphp
                    <tr>
                        <td><input type="checkbox" name="fields[]" value="{{ $k }}" @checked(! $horse || ! $current) class="rounded" aria-label="Importer {{ $fields[$k] }}"></td>
                        <td class="font-medium">{{ $fields[$k] }}</td>
                        <td>{{ $k === 'sex' ? ($sexes[$v] ?? $v) : $v }}</td>
                        @if ($horse)<td class="{{ $current && (string) $current !== (string) $v ? 'text-amber-700' : 'text-slate-500' }}">{{ $current ?? '—' }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @error('fields')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mt-3 text-xs text-slate-500">Seules les cases cochées sont importées. Elles seront enregistrées avec leur provenance ({{ $candidate->sourceLabel }}, {{ now()->format('d/m/Y') }}) et restent modifiables.</p>
        </x-section>
        @unless ($horse || isset($candidate->fields['official_name']))<x-field name="fallback_name" label="Nom du cheval" required />@endunless
        <div class="flex gap-2"><button class="btn-primary">Confirmer l'importation</button><a href="{{ route('horse-search.form') }}" class="btn-ghost">Annuler</a></div>
    </form>
</x-layouts.app>
