@php
    $perms = $perms ?? app(\App\Services\HorseAccess::class)->permissions(auth()->user(), $horse);
    $tabs = [
        ['horses.show', 'Fiche', true],
        ['horses.care.index', 'Santé', in_array('health.view', $perms) || in_array('treatments.view', $perms)],
        ['sessions.index', 'Séances', in_array('sessions.view', $perms), ['horse' => $horse->id]],
        ['horses.feeding', 'Alimentation', in_array('feeding.view', $perms)],
        ['horses.logs.index', 'Suivi', true],
        ['horses.weight', 'Poids', true],
        ['horses.documents.index', 'Documents', in_array('documents.view', $perms), null, 'documents'],
        ['horses.pedigree', 'Généalogie', true],
        ['horses.professionals', 'Intervenants', true],
        ['budget.index', 'Dépenses', in_array('expenses.view', $perms), ['horse' => $horse->id], 'budget'],
        ['horses.riding-days', 'Jours de monte', in_array('sessions.view', $perms)],
        ['horses.sharing', 'Partage', in_array('horse.manage', $perms)],
    ];
@endphp
@php
    $visible = collect($tabs)->filter(fn ($t) => $t[2])->map(function ($t) use ($horse) {
        $params = $t[3] ?? $horse;
        $active = request()->routeIs($t[0]) && (! isset($t[3]) || request('horse') == $horse->id);
        // Fonction non incluse dans l'offre de l'espace du cheval : onglet signalé (l'onglet présente l'offre qui l'inclut).
        $locked = isset($t[4]) && ! app(\App\Services\Entitlements::class)->hasFeature($horse->organization, $t[4]);

        return ['url' => route($t[0], $params), 'label' => $t[1], 'active' => $active, 'locked' => $locked];
    })->values();
    // Téléphone : 4 onglets principaux et « Plus » ; l'onglet ouvert reste visible même s'il fait partie de « Plus ».
    $primary = $visible->take(4);
    $active = $visible->firstWhere('active', true);
    if ($active && ! $primary->contains('label', $active['label'])) {
        $primary = $primary->take(3)->push($active);
    }
    $more = $visible->reject(fn ($t) => $primary->contains('label', $t['label']))->values();
    $pill = fn ($t) => 'pill relative min-h-11 px-3.5 sm:min-h-9 '.($t['active'] ? 'bg-brand-800 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900');
    $lock = fn ($t) => $t['locked'] ? '<svg class="ml-1 inline size-3.5 '.($t['active'] ? 'text-gold-200' : 'text-gold-500').'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/></svg><span class="sr-only"> (offre supérieure)</span>' : '';
@endphp
{{-- Ordinateur : tous les onglets, sur plusieurs lignes si besoin (plus d'onglet coupé). --}}
<nav class="mb-6 hidden sm:block" aria-label="Sections du dossier">
    <div class="flex flex-wrap gap-1.5 rounded-3xl bg-white p-1 shadow-sm ring-1 ring-slate-200/70">
        @foreach ($visible as $t)
            <a href="{{ $t['url'] }}" @if ($t['active']) aria-current="page" @endif class="{{ $pill($t) }}">{{ $t['label'] }}{!! $lock($t) !!}</a>
        @endforeach
    </div>
</nav>
{{-- Téléphone : 4 onglets + « Plus » (feuille avec les autres sections) ; largeur selon le libellé, qui passe sur deux lignes plutôt que d'être coupé. --}}
<nav class="jc-sticky-strip mb-6 sm:hidden" aria-label="Sections du dossier (téléphone)" x-data="{ more: false }" @keydown.escape.window="more = false" data-horse-tabs-mobile>
    <div class="flex gap-1 rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200/70">
        @foreach ($primary as $t)
            <a href="{{ $t['url'] }}" @if ($t['active']) aria-current="page" @endif class="{{ $pill($t) }} flex-auto justify-center px-2 text-center text-[13px] leading-tight whitespace-normal">{{ $t['label'] }}{!! $lock($t) !!}</a>
        @endforeach
        @if ($more->isNotEmpty())
            <button type="button" class="pill min-h-11 flex-auto justify-center px-2 text-[13px] text-slate-600" @click="more = true" :aria-expanded="more" data-horse-tabs-more>Plus</button>
        @endif
    </div>
    @if ($more->isNotEmpty())
        <template x-teleport="body">
            <div x-show="more" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-end bg-slate-900/45" @click.self="more = false" role="dialog" aria-modal="true" aria-label="Autres sections">
                <div class="w-full rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl">
                    <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-semibold">{{ $horse->shortName() }}</h2><button type="button" class="btn-ghost px-2" @click="more = false" aria-label="Fermer"><x-icon name="x" /></button></div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($more as $t)
                            <a href="{{ $t['url'] }}" class="flex min-h-14 items-center rounded-2xl bg-slate-50 px-4 font-medium text-slate-800 hover:bg-brand-50">{{ $t['label'] }}{!! $lock($t) !!}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </template>
    @endif
</nav>
