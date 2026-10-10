@php $fields = \App\Services\HorseSearch\HorseCandidate::FIELDS; $sexes = \App\Models\Horse::SEXES; @endphp
<x-layouts.app title="Résultats de recherche">
    <x-page-header title="Résultats pour « {{ $query['name'] }} »" :subtitle="count($candidates).' résultat(s) · sources : '.implode(', ', $sources)" :back="route('horse-search.form')" />
    @foreach ($sourceErrors as $source => $msg)<div class="mb-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $source }} : {{ $msg }}</div>@endforeach
    @if (empty($candidates))
        <x-empty title="Aucune information fiable trouvée" icon="search">Aucune source n'a renvoyé de résultat. Vous pouvez saisir la fiche manuellement.
            <x-slot:actions><a href="{{ $targetHorse ? route('horses.edit', $targetHorse) : route('horses.create') }}" class="btn-primary">Saisie manuelle</a></x-slot:actions>
        </x-empty>
    @else
        @if (count($candidates) > 1)<p class="mb-3 text-sm text-slate-600">Plusieurs chevaux peuvent porter ce nom : vérifiez chaque source avant de choisir. Les champs <span class="rounded bg-amber-100 px-1">surlignés</span> diffèrent d'un résultat à l'autre.</p>@endif
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($candidates as $c)
                <div class="card flex flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ $c->fields['official_name'] ?? 'Sans nom' }}</p>
                        <x-badge :color="['official' => 'green', 'reference' => 'blue', 'suggested' => 'amber'][$c->reliability]">{{ ['official' => 'Source officielle', 'reference' => 'Source de référence', 'suggested' => 'Simple suggestion'][$c->reliability] }}</x-badge>
                    </div>
                    <p class="text-xs text-slate-500">{{ $c->sourceLabel }} @if ($c->url)· <a href="{{ $c->url }}" target="_blank" rel="noopener nofollow" class="link">consulter la source</a>@endif</p>
                    @if ($c->snippet)<p class="mt-2 text-sm text-slate-600">{{ $c->snippet }}</p>@endif
                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                        @foreach ($c->fields as $k => $v)
                            @if ($k !== 'official_name')<div class="{{ in_array($k, $differing) ? 'rounded bg-amber-50' : '' }}"><dt class="text-xs text-slate-500">{{ $fields[$k] }}</dt><dd>{{ $k === 'sex' ? ($sexes[$v] ?? $v) : $v }}</dd></div>@endif
                        @endforeach
                    </dl>
                    <form method="POST" action="{{ route('horse-search.preview') }}" class="mt-auto pt-3">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="candidate" value="{{ $c->id }}">
                        @if ($targetHorse)<input type="hidden" name="horse_id" value="{{ $targetHorse->id }}">@endif
                        <button class="btn-secondary w-full">C'est mon cheval</button>
                    </form>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-sm"><a href="{{ $targetHorse ? route('horses.edit', $targetHorse) : route('horses.create') }}" class="link">Aucun ne correspond : saisir manuellement</a></p>
    @endif
</x-layouts.app>
