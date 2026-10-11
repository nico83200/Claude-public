<x-layouts.app title="Exercices">
    <x-page-header title="Exercices" :subtitle="$exercises->total().' exercice(s)'">
        <a href="{{ route('exercises.create') }}" class="btn-primary"><x-icon name="plus" /> Créer</a>
    </x-page-header>
    <x-filters search="q" placeholder="Nom, objectif, tag…" :fields="['category', 'level', 'discipline', 'scope', 'max_minutes', 'objective', 'archived']">
        <x-select name="category" label="Catégorie" :options="$categories" :value="request('category')" placeholder="Toutes" />
        <x-select name="level" label="Niveau" :options="\App\Models\Exercise::LEVELS" :value="request('level')" placeholder="Tous" />
        <x-select name="discipline" label="Discipline" :options="\App\Models\Horse::DISCIPLINES + ['flat' => 'Travail sur le plat', 'all' => 'Toutes']" :value="request('discipline')" placeholder="Toutes" />
        <x-select name="scope" label="Origine" :options="\App\Models\Exercise::SCOPES" :value="request('scope')" placeholder="Toutes" />
        <x-select name="max_minutes" label="Durée" :options="[5 => '≤ 5 min', 10 => '≤ 10 min', 15 => '≤ 15 min', 30 => '≤ 30 min']" :value="request('max_minutes')" placeholder="Toutes" />
        <div><label class="label" for="f_objective">Objectif</label><input id="f_objective" name="objective" value="{{ request('objective') }}" class="input"></div>
        <label class="col-span-2 flex min-h-11 items-center gap-3 text-sm sm:col-span-1 sm:self-end"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="size-5 rounded"> Archivés</label>
    </x-filters>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($exercises as $ex)
            <a href="{{ route('exercises.show', $ex) }}" class="card flex flex-col p-4 transition hover:shadow-md">
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
