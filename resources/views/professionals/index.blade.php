<x-layouts.app title="Intervenants">
    <x-page-header title="Intervenants" :subtitle="'Répertoire de '.$org->name">
        @if ($canManage)<a href="{{ route('professionals.create') }}" class="btn-primary"><x-icon name="plus" /> Ajouter</a>@endif
    </x-page-header>
    <form class="mb-4 flex flex-wrap gap-2" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, société…" class="input max-w-xs" aria-label="Rechercher">
        <x-select name="kind" :options="\App\Models\Professional::KINDS" :value="request('kind')" placeholder="Toutes fonctions" aria-label="Fonction" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="rounded"> Archivés</label>
        <button class="btn-secondary">Filtrer</button>
    </form>
    @if ($professionals->isEmpty())
        <x-empty title="Aucun intervenant" icon="users">Vétérinaires, maréchaux-ferrants, ostéopathes… Une fiche unique par professionnel, associée aux chevaux concernés.</x-empty>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($professionals as $p)
                <a href="{{ route('professionals.show', $p) }}" class="card p-4 hover:border-brand-300">
                    <p class="font-semibold">{{ $p->fullName() }}</p>
                    <p class="text-sm text-slate-500">{{ $p->kindLabel() }}{{ $p->company ? ' · '.$p->company : '' }}</p>
                    <p class="mt-1 text-sm">{{ $p->phone }}</p>
                    <p class="text-xs text-slate-400">{{ $p->horses_count }} cheval(aux)</p>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $professionals->links() }}</div>
    @endif
</x-layouts.app>
