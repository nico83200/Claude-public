@php
    $perms = $perms ?? app(\App\Services\HorseAccess::class)->permissions(auth()->user(), $horse);
    $tabs = [
        ['horses.show', 'Fiche', true],
        ['horses.care.index', 'Santé', in_array('health.view', $perms) || in_array('treatments.view', $perms)],
        ['sessions.index', 'Séances', in_array('sessions.view', $perms), ['horse' => $horse->id]],
        ['horses.feeding', 'Alimentation', in_array('feeding.view', $perms)],
        ['horses.logs.index', 'Suivi quotidien', true],
        ['horses.documents.index', 'Documents', in_array('documents.view', $perms)],
        ['horses.pedigree', 'Généalogie', true],
        ['horses.professionals', 'Intervenants', true],
        ['budget.index', 'Dépenses', in_array('expenses.view', $perms), ['horse' => $horse->id]],
        ['horses.sharing', 'Partage', in_array('horse.manage', $perms)],
    ];
@endphp
<nav class="-mx-4 mb-5 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0" aria-label="Sections du dossier">
    <div class="flex gap-1">
        @foreach ($tabs as $tab)
            @if ($tab[2])
                @php $params = $tab[3] ?? $horse; $active = request()->routeIs($tab[0]) && (! isset($tab[3]) || request('horse') == $horse->id); @endphp
                <a href="{{ route($tab[0], $params) }}" class="shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium {{ $active ? 'border-brand-600 text-brand-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $tab[1] }}</a>
            @endif
        @endforeach
    </div>
</nav>
