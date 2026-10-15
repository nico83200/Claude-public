<x-layouts.app :title="$single ? 'Cheval' : 'Chevaux'">
    <x-page-header :title="$single ? 'Cheval' : 'Chevaux'" :subtitle="$single ? null : $horses->total().' dossier(s)'">
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
    @elseif ($single)
        {{-- Un seul cheval : sa carte en grand, sur toute la largeur. --}}
        @php $horse = $single; $photo = $horse->mainPhotoUrl(false); @endphp
        <a href="{{ route('horses.show', $horse) }}" class="card group block overflow-hidden transition hover:shadow-md" data-single-horse>
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $horse->shortName() }}" class="h-56 w-full object-cover sm:h-80">
            @else
                <div class="flex h-40 w-full items-center justify-center bg-brand-100 text-6xl font-bold text-brand-800 sm:h-56">{{ mb_substr($horse->shortName(), 0, 1) }}</div>
            @endif
            <div class="flex items-center gap-4 p-5 sm:p-6">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-2xl font-semibold text-slate-900">{{ $horse->shortName() }}</p>
                    @if ($horse->usual_name && $horse->official_name !== $horse->shortName())<p class="truncate text-sm text-slate-500">{{ $horse->official_name }}</p>@endif
                    <p class="mt-1 text-slate-600">{{ collect([$horse->breed?->name, $horse->age() !== null ? $horse->age().' ans' : null, \App\Models\Horse::SEXES[$horse->sex] ?? null])->filter()->implode(' · ') }}</p>
                    <p class="truncate text-sm text-slate-400">{{ $horse->organization->name }}</p>
                </div>
                <span class="btn-secondary shrink-0 group-hover:bg-slate-50">Ouvrir le dossier <x-icon name="chevron" class="size-4" /></span>
            </div>
        </a>
        <x-upsell :offer="$upsell" class="mt-6" />
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
        <x-upsell :offer="$upsell" class="mt-6" />
    @endif
</x-layouts.app>
