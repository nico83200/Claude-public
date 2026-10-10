<x-layouts.app title="Exercices">
    <x-page-header title="Bibliothèque d'exercices" :subtitle="$exercises->total().' exercice(s)'">
        <a href="{{ route('exercises.create') }}" class="btn-primary"><x-icon name="plus" /> Créer</a>
    </x-page-header>
    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, objectif, tag…" class="input col-span-2 sm:max-w-xs" aria-label="Rechercher">
        <x-select name="category" :options="$categories" :value="request('category')" placeholder="Catégorie" aria-label="Catégorie" />
        <x-select name="level" :options="\App\Models\Exercise::LEVELS" :value="request('level')" placeholder="Niveau" aria-label="Niveau" />
        <x-select name="discipline" :options="\App\Models\Horse::DISCIPLINES + ['flat' => 'Travail sur le plat', 'all' => 'Toutes']" :value="request('discipline')" placeholder="Discipline" aria-label="Discipline" />
        <x-select name="scope" :options="\App\Models\Exercise::SCOPES" :value="request('scope')" placeholder="Origine" aria-label="Origine" />
        <x-select name="max_minutes" :options="[5 => '≤ 5 min', 10 => '≤ 10 min', 15 => '≤ 15 min', 30 => '≤ 30 min']" :value="request('max_minutes')" placeholder="Durée" aria-label="Durée maximale" />
        <input name="objective" value="{{ request('objective') }}" placeholder="Objectif" class="input sm:max-w-40" aria-label="Objectif">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="rounded"> Archivés</label>
        <button class="btn-secondary">Filtrer</button>
    </form>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($exercises as $ex)
            <a href="{{ route('exercises.show', $ex) }}" class="card flex flex-col p-4 hover:border-brand-300">
                <div class="flex items-start justify-between gap-2"><p class="font-semibold">{{ $ex->name }}</p><x-badge :color="['default' => 'slate', 'personal' => 'brand', 'organization' => 'purple'][$ex->scope]">{{ ['default' => 'Défaut', 'personal' => 'Perso', 'organization' => 'Écurie'][$ex->scope] }}</x-badge></div>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $ex->objective }}</p>
                <p class="mt-auto pt-2 text-xs text-slate-500">{{ $ex->category?->name }} · {{ \App\Models\Exercise::LEVELS[$ex->level] }}{{ $ex->duration_minutes ? ' · '.$ex->duration_minutes.' min' : '' }}</p>
                @if ($ex->tags->isNotEmpty())<div class="mt-1 flex flex-wrap gap-1">@foreach ($ex->tags as $t)<span class="chip bg-slate-100 text-slate-600">#{{ $t->name }}</span>@endforeach</div>@endif
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3"><x-empty title="Aucun exercice ne correspond" icon="book" /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $exercises->links() }}</div>
</x-layouts.app>
