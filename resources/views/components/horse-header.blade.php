@props(['horse', 'perms' => null])
@php
    // En-tête commun à tous les onglets du dossier d'un cheval : identité, actions de la page, menu « ⋯ », onglets.
    $perms = $perms ?? app(\App\Services\HorseAccess::class)->permissions(auth()->user(), $horse);
    $can = fn ($p) => in_array($p, $perms, true);
    $meta = collect([$horse->breed?->name, \App\Models\Horse::SEXES[$horse->sex] ?? null, $horse->age() !== null ? $horse->age().' ans' : null, $horse->coat])->filter();
@endphp
@if (! empty($healthHub))
    {{-- Ouvert depuis la page Santé du menu : en-tête de la santé (onglets, choix du cheval), actions de la page conservées. --}}
    <x-page-header title="Santé et soins" :subtitle="$horse->shortName().' · '.(['sante' => 'soins et traitements', 'alimentation' => 'alimentation', 'suivi' => 'suivi quotidien', 'poids' => 'poids et état corporel'][$healthHub['tab']] ?? '')">
        {{ $slot }}
    </x-page-header>
    @include('health._hub-tabs')
@else
<div class="mb-4" x-data="{ more: false }">
    @if (app(\App\Services\HorseAccess::class)->focusHorse(auth()->user())?->is($horse))
        <a href="{{ route('dashboard') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="back" class="size-4" /> Accueil</a>
    @else
        <a href="{{ route('horses.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800"><x-icon name="back" class="size-4" /> Chevaux</a>
    @endif
    <div class="flex items-center gap-4">
        <a href="{{ route('horses.show', $horse) }}" class="shrink-0" aria-label="Fiche de {{ $horse->shortName() }}">
            @if ($horse->mainPhotoUrl())
                <img src="{{ $horse->mainPhotoUrl() }}" alt="" class="size-16 rounded-2xl object-cover sm:size-20">
            @else
                <span class="flex size-16 items-center justify-center rounded-2xl bg-brand-100 text-2xl font-bold text-brand-800 sm:size-20">{{ mb_strtoupper(mb_substr($horse->shortName(), 0, 1)) }}</span>
            @endif
        </a>
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl leading-tight font-bold break-words text-slate-900 sm:text-[28px]">{{ $horse->shortName() }}</h1>
            @if ($horse->usual_name)<p class="truncate text-sm text-slate-500">{{ $horse->official_name }}</p>@endif
            @if ($meta->isNotEmpty())<p class="mt-0.5 text-sm text-slate-500">{{ $meta->implode(' · ') }}</p>@endif
            @if ($horse->isArchived())<x-badge color="amber" class="mt-1">Archivé — lecture seule</x-badge>@endif
        </div>
        <div class="relative shrink-0" @keydown.escape.window="more = false">
            <button type="button" class="btn-icon" @click="more = ! more" :aria-expanded="more" aria-haspopup="menu" aria-label="Plus d'actions"><x-icon name="dots" /></button>
            <div x-cloak x-show="more" x-transition.origin.top.right @click.outside="more = false" role="menu"
                 class="absolute right-0 z-40 mt-2 w-64 rounded-2xl border border-slate-100 bg-white p-1.5 shadow-xl">
                @if ($can('horse.edit') && ! $horse->isArchived())
                    <a href="{{ route('horses.edit', $horse) }}" class="menu-item" role="menuitem"><x-icon name="pencil" class="size-4 text-slate-400" /> Modifier la fiche</a>
                    <a href="{{ route('horses.emergency.edit', $horse) }}" class="menu-item" role="menuitem" data-emergency-menu><x-icon name="alert" class="size-4 text-slate-400" /> Fiche d'urgence (QR code)</a>
                @endif
                <a href="{{ route('horses.export.pdf', $horse) }}" class="menu-item" role="menuitem"><x-icon name="download" class="size-4 text-slate-400" /> Télécharger la fiche (PDF) <x-locked-mark :org="$horse->organization" feature="exports" /></a>
                @if ($can('horse.manage'))
                    <a href="{{ route('horses.sharing', $horse) }}" class="menu-item" role="menuitem"><x-icon name="share" class="size-4 text-slate-400" /> Partager</a>
                    <a href="{{ route('horses.history', $horse) }}" class="menu-item" role="menuitem"><x-icon name="clock" class="size-4 text-slate-400" /> Historique des accès</a>
                    <div class="my-1 border-t border-slate-100"></div>
                    @if ($horse->isArchived())
                        <form method="POST" action="{{ route('horses.unarchive', $horse) }}">@csrf<button class="menu-item" role="menuitem"><x-icon name="refresh" class="size-4 text-slate-400" /> Restaurer le dossier</button></form>
                    @else
                        <form method="POST" action="{{ route('horses.archive', $horse) }}" data-confirm="Archiver ce cheval ? Son dossier sera conservé en lecture seule." data-confirm-ok="Archiver">@csrf<button class="menu-item" role="menuitem"><x-icon name="archive" class="size-4 text-slate-400" /> Archiver (départ, vente…)</button></form>
                    @endif
                    <a href="{{ route('horses.show', $horse) }}#gestion" class="menu-item text-red-600" role="menuitem"><x-icon name="trash" class="size-4" /> Transférer ou supprimer…</a>
                @endif
            </div>
        </div>
    </div>
    @if (trim($slot))
        <div class="mt-4 flex flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
@include('horses._tabs', ['perms' => $perms])
@endif
