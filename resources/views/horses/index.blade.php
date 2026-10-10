<x-layouts.app title="Chevaux">
    <x-page-header title="Chevaux" :subtitle="$horses->total().' dossier(s)'">
        <a href="{{ route('horse-search.form') }}" class="btn-secondary"><x-icon name="search" /> Rechercher mon cheval</a>
        @if ($canCreate)<a href="{{ route('horses.create') }}" class="btn-primary"><x-icon name="plus" /> Ajouter</a>@endif
    </x-page-header>
    <form class="mb-4 flex flex-wrap gap-2" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, SIRE, UELN…" class="input max-w-xs" aria-label="Rechercher">
        @if ($userMemberships->count() > 1)
            <select name="organization" class="input max-w-xs" aria-label="Espace"><option value="">Tous les espaces</option>@foreach ($userMemberships as $m)<option value="{{ $m->organization_id }}" @selected(request('organization') == $m->organization_id)>{{ $m->organization->name }}</option>@endforeach</select>
        @endif
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="rounded"> Archivés</label>
        <button class="btn-secondary">Filtrer</button>
    </form>
    @if ($horses->isEmpty())
        <x-empty title="Aucun cheval" icon="horse">Ajoutez un cheval ou demandez au propriétaire de vous inviter.
            @if ($canCreate)
            <x-slot:actions><a href="{{ route('horses.create') }}" class="btn-primary">Ajouter un cheval</a></x-slot:actions>
            @endif
        </x-empty>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($horses as $horse)
                <a href="{{ route('horses.show', $horse) }}" class="card flex items-center gap-4 p-4 hover:border-brand-300">
                    @if ($horse->mainPhotoUrl())<img src="{{ $horse->mainPhotoUrl() }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-xl object-cover">@else<div class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-2xl font-bold text-brand-800">{{ mb_substr($horse->shortName(), 0, 1) }}</div>@endif
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $horse->shortName() }}</p>
                        <p class="truncate text-sm text-slate-500">{{ collect([$horse->breed?->name, $horse->age() !== null ? $horse->age().' ans' : null, \App\Models\Horse::SEXES[$horse->sex] ?? null])->filter()->implode(' · ') }}</p>
                        <p class="truncate text-xs text-slate-400">{{ $horse->organization->name }}</p>
                        <div class="mt-1 flex gap-1">
                            @if (in_array($horse->id, $offline))<x-badge color="brand">Hors ligne</x-badge>@endif
                            @if ($horse->isArchived())<x-badge>Archivé</x-badge>@endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $horses->links() }}</div>
    @endif
</x-layouts.app>
