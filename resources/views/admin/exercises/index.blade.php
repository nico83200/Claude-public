<x-layouts.app title="Exercices · Admin">
    <x-page-header title="Bibliothèque d'exercices" :subtitle="$counts['total'].' exercices proposés à tous · '.$counts['media'].' avec une démonstration'">
        <a href="{{ route('admin.exercises.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Nouvel exercice</a>
    </x-page-header>
    @include('admin._nav')

    <form method="GET" class="mb-4 grid gap-2 sm:grid-cols-[1fr_auto_auto_auto]">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un exercice…" class="input" aria-label="Rechercher">
        <select name="categorie" class="input" aria-label="Catégorie" onchange="this.form.submit()"><option value="">Toutes les catégories</option>@foreach ($categories as $id => $name)<option value="{{ $id }}" @selected(request('categorie') == $id)>{{ $name }}</option>@endforeach</select>
        <select name="media" class="input" aria-label="Démonstration" onchange="this.form.submit()"><option value="">Avec ou sans démonstration</option><option value="avec" @selected(request('media') === 'avec')>Avec démonstration</option><option value="sans" @selected(request('media') === 'sans')>Sans démonstration</option></select>
        <label class="flex items-center gap-2 px-2 text-sm"><input type="checkbox" name="archives" value="1" @checked(request()->boolean('archives')) onchange="this.form.submit()" class="size-5 rounded"> Retirés</label>
    </form>

    @if ($exercises->isEmpty())
        <x-empty title="Aucun exercice" icon="book">Modifiez la recherche ou ajoutez un exercice.</x-empty>
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Exercice</th><th class="hidden sm:table-cell">Catégorie</th><th class="hidden md:table-cell">Niveau</th><th>Démonstration</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($exercises as $e)
                <tr>
                    <td><a href="{{ route('admin.exercises.edit', $e) }}" class="font-medium link">{{ $e->name }}</a><span class="block text-xs text-slate-500">{{ $e->objective }}</span></td>
                    <td class="hidden text-sm sm:table-cell">{{ $e->category?->name }}</td>
                    <td class="hidden text-sm md:table-cell">{{ \App\Models\Exercise::LEVELS[$e->level] ?? '' }}</td>
                    <td>@if ($e->media_type === 'video')<x-badge color="green">Vidéo</x-badge>@elseif ($e->media_type === 'image')<x-badge color="green">Image</x-badge>@else<span class="text-xs text-slate-400">—</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="mt-4">{{ $exercises->links() }}</div>
    @endif
</x-layouts.app>
