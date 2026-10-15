<x-layouts.app title="Intervenants">
    <x-page-header title="Intervenants" :subtitle="'Répertoire de '.$org->name">
        @if ($canManage)<a href="{{ route('professionals.create') }}" class="btn-secondary"><x-icon name="users" class="size-4" /> Importer un contact</a><a href="{{ route('professionals.create') }}" class="btn-primary"><x-icon name="plus" /> Ajouter</a>@endif
    </x-page-header>
    <form class="mb-4 flex flex-wrap gap-2" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, société…" class="input max-w-xs" aria-label="Rechercher">
        <x-select name="kind" :options="\App\Models\Professional::KINDS" :value="request('kind')" placeholder="Toutes fonctions" aria-label="Fonction" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="rounded"> Archivés</label>
        <button class="btn-secondary">Filtrer</button>
    </form>
    @if ($professionals->isEmpty() && $shared->isEmpty())
        <x-empty title="Aucun intervenant" icon="users">Vétérinaires, maréchaux-ferrants, ostéopathes… Une fiche unique par professionnel, associée aux chevaux concernés.</x-empty>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($professionals as $p)
                <div class="card relative p-4 hover:border-brand-300">
                    <a href="{{ route('professionals.show', $p) }}" class="absolute inset-0 rounded-3xl" aria-label="{{ $p->fullName() }}"></a>
                    <p class="pr-12 font-semibold">{{ $p->fullName() }}</p>
                    <p class="pr-12 text-sm text-slate-500">{{ $p->kindLabel() }}{{ $p->company ? ' · '.$p->company : '' }}</p>
                    <p class="mt-1 text-sm">{{ $p->phone }}</p>
                    <p class="text-xs text-slate-400">{{ $p->horses_count }} cheval(aux)</p>
                    @if ($canManage && $p->organization_id === $org->id)
                        {{-- Accès direct à la modification de la fiche. --}}
                        <a href="{{ route('professionals.edit', $p) }}" class="absolute top-2 right-2 z-10 flex size-11 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-brand-700" aria-label="Modifier {{ $p->fullName() }}" title="Modifier" data-edit-professional="{{ $p->id }}"><x-icon name="pencil" class="size-5" /></a>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $professionals->links() }}</div>
    @endif
    @if ($shared->isNotEmpty())
        <h2 class="mt-8 mb-1 text-lg font-semibold">Intervenants des chevaux partagés avec vous</h2>
        <p class="mb-3 text-sm text-slate-500">Les contacts que les propriétaires ont associés à leurs chevaux. Consultation seule : ils restent gérés par leur espace.</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-shared-professionals>
            @foreach ($shared as $p)
                <a href="{{ route('professionals.show', $p) }}" class="card p-4 hover:border-brand-300" data-shared-professional="{{ $p->id }}">
                    <p class="font-semibold">{{ $p->fullName() }}</p>
                    <p class="text-sm text-slate-500">{{ $p->kindLabel() }}{{ $p->company ? ' · '.$p->company : '' }}</p>
                    <p class="mt-1 text-sm">{{ $p->phone }}</p>
                    <p class="text-xs text-slate-400">Partagé par {{ $p->organization->name }}</p>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
