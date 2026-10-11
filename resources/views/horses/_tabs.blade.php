@php
    $perms = $perms ?? app(\App\Services\HorseAccess::class)->permissions(auth()->user(), $horse);
    $tabs = [
        ['horses.show', 'Fiche', true],
        ['horses.care.index', 'Santé', in_array('health.view', $perms) || in_array('treatments.view', $perms)],
        ['sessions.index', 'Séances', in_array('sessions.view', $perms), ['horse' => $horse->id]],
        ['horses.feeding', 'Alimentation', in_array('feeding.view', $perms)],
        ['horses.logs.index', 'Suivi', true],
        ['horses.documents.index', 'Documents', in_array('documents.view', $perms), null, 'documents'],
        ['horses.pedigree', 'Généalogie', true],
        ['horses.professionals', 'Intervenants', true],
        ['budget.index', 'Dépenses', in_array('expenses.view', $perms), ['horse' => $horse->id], 'budget'],
        ['horses.sharing', 'Partage', in_array('horse.manage', $perms)],
    ];
@endphp
<nav class="no-scrollbar -mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Sections du dossier"
     x-data x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' })">
    <div class="flex w-max gap-1.5 rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200/70">
        @foreach ($tabs as $tab)
            @if ($tab[2])
                @php
                    $params = $tab[3] ?? $horse; $active = request()->routeIs($tab[0]) && (! isset($tab[3]) || request('horse') == $horse->id);
                    // Fonction non incluse dans l'offre de l'espace du cheval : onglet signalé (l'onglet présente l'offre qui l'inclut).
                    $locked = isset($tab[4]) && ! app(\App\Services\Entitlements::class)->hasFeature($horse->organization, $tab[4]);
                @endphp
                <a href="{{ route($tab[0], $params) }}" @if ($active) aria-current="page" @endif
                   class="pill relative min-h-9 px-3.5 {{ $active ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">{{ $tab[1] }}@if ($locked)<x-icon name="sparkles" class="ml-1 inline size-3.5 {{ $active ? 'text-gold-200' : 'text-gold-500' }}" /><span class="sr-only"> (offre supérieure)</span>@endif</a>
            @endif
        @endforeach
    </div>
</nav>
