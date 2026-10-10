<x-layouts.app title="Rechercher mon cheval">
    <x-page-header title="Rechercher mon cheval" subtitle="Pré-remplissez la fiche à partir de sources publiques" :back="route('horses.index')" />
    @unless ($included)<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">La recherche d'identité n'est pas incluse dans l'offre actuelle. Vous pouvez <a href="{{ route('horses.create') }}" class="link">saisir la fiche manuellement</a>.</div>@endunless
    <div class="grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('horse-search.search') }}" class="space-y-4 lg:col-span-2">
            @csrf
            @if ($targetHorse)<input type="hidden" name="horse_id" value="{{ $targetHorse->id }}"><p class="text-sm">Les informations choisies compléteront la fiche de <strong>{{ $targetHorse->shortName() }}</strong>.</p>@endif
            <x-section>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="name" label="Nom du cheval" :value="$targetHorse?->official_name" required class="sm:col-span-2" />
                    <x-field name="sire" label="N° SIRE" :value="$targetHorse?->identifier('sire')" />
                    <x-field name="ueln" label="UELN" :value="$targetHorse?->identifier('ueln')" />
                    <x-field name="breed" label="Race" :value="$targetHorse?->breed?->name" />
                    <x-field name="birth_year" type="number" label="Année de naissance" :value="$targetHorse?->birth_year ?? $targetHorse?->birth_date?->year" />
                    <x-field name="sire_name" label="Nom du père" />
                    <x-field name="dam_name" label="Nom de la mère" />
                    <x-field name="country" label="Pays d'origine" />
                </div>
            </x-section>
            <button class="btn-primary" @disabled(! $included)><x-icon name="search" /> Rechercher</button>
        </form>
        <x-section title="Comment ça marche ?">
            <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                <li>Les sources publiques autorisées sont interrogées.</li>
                <li>Vous comparez les résultats (les différences sont signalées).</li>
                <li>Vous choisissez le bon cheval et les informations à importer.</li>
                <li>Chaque information conserve sa source et sa date.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500">Sources actives : {{ implode(', ', $sources) ?: 'aucune' }}. Rien n'est importé sans votre validation, et aucune donnée manquante n'est inventée. Pour une vérification officielle de l'identité (SIRE), consultez le site de l'IFCE.</p>
        </x-section>
    </div>
</x-layouts.app>
