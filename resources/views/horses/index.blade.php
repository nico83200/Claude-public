<x-layouts.app title="Chevaux">
    <x-page-header title="Chevaux" :subtitle="$horses->total().' dossier(s)'">
        <a href="{{ route('horse-search.form') }}" class="btn-secondary">Importer une fiche</a>
        @if ($canCreate)<a href="{{ route('horses.create') }}" class="btn-primary"><x-icon name="plus" /> Ajouter</a>@endif
    </x-page-header>
    <x-filters search="q" placeholder="Nom, SIRE, UELN…" :fields="$userMemberships->count() > 1 ? ['organization', 'archived'] : ['archived']">
        @if ($userMemberships->count() > 1)
            <x-select name="organization" label="Espace" :options="$userMemberships->mapWithKeys(fn ($m) => [$m->organization_id => $m->organization->name])" :value="request('organization')" placeholder="Tous" />
        @endif
        <label class="col-span-2 flex min-h-11 items-center gap-3 text-sm sm:col-span-1 sm:self-end"><input type="checkbox" name="archived" value="1" @checked(request('archived')) class="size-5 rounded"> Afficher les chevaux archivés</label>
    </x-filters>
    @if ($horses->isEmpty())
        <x-empty title="Aucun cheval" icon="horse">Ajoutez un cheval ou demandez au propriétaire de vous inviter.
            @if ($canCreate)
            <x-slot:actions><a href="{{ route('horses.create') }}" class="btn-primary">Ajouter un cheval</a></x-slot:actions>
            @endif
        </x-empty>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($horses as $horse)
                <a href="{{ route('horses.show', $horse) }}" class="card flex items-center gap-4 p-4 transition hover:shadow-md">
                    @if ($horse->mainPhotoUrl())<img src="{{ $horse->mainPhotoUrl() }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-xl object-cover">@else<div class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-2xl font-bold text-brand-800">{{ mb_substr($horse->shortName(), 0, 1) }}</div>@endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $horse->shortName() }}</p>
                        <p class="truncate text-sm text-slate-500">{{ collect([$horse->breed?->name, $horse->age() !== null ? $horse->age().' ans' : null, \App\Models\Horse::SEXES[$horse->sex] ?? null])->filter()->implode(' · ') }}</p>
                        <p class="truncate text-xs text-slate-400">{{ $horse->organization->name }}</p>
                        @if ($horse->isArchived())<x-badge class="mt-1">Archivé</x-badge>@endif
                    </div>
                    <x-icon name="chevron" class="size-5 shrink-0 text-slate-300" />
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $horses->links() }}</div>
    @endif
</x-layouts.app>
