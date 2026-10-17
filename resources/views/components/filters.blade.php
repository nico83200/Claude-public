@props(['fields' => [], 'search' => null, 'placeholder' => 'Rechercher…', 'keep' => [], 'sticky' => true])
@php
    // Barre de filtres compacte : recherche toujours visible, critères repliés derrière « Filtres ».
    // Les listes déroulantes s'appliquent dès qu'on les change ; « Effacer » remet tout à zéro.
    // Téléphone : avec une recherche, la barre reste sous l'en-tête pendant le défilement (repliée seulement).
    $active = collect($fields)->filter(fn ($f) => filled(request($f)))->count();
    $resetParams = collect($keep)->filter(fn ($v) => filled($v))->all();
@endphp
<form method="GET" {{ $attributes->merge(['class' => 'mb-5']) }} x-data="{ open: {{ $active ? 'true' : 'false' }} }" @if ($sticky && $search) :class="{ 'jc-sticky-strip': ! open }" @endif
      @submit="[...$el.elements].forEach((e) => { if (e.name && e.value === '') e.disabled = true })"
      @change="if ($event.target.tagName === 'SELECT' || $event.target.type === 'checkbox') $el.requestSubmit()">
    @foreach ($keep as $k => $v)@if (filled($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
    <div class="flex items-center gap-2">
        @if ($search)
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                <input type="search" name="{{ $search }}" value="{{ request($search) }}" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" class="input rounded-full pl-11" enterkeyhint="search">
            </div>
        @endif
        @if ($fields)
            <button type="button" @click="open = ! open" :aria-expanded="open"
                    class="btn-secondary shrink-0 {{ $active ? 'border-brand-300 bg-brand-50 text-brand-800' : '' }}">
                <x-icon name="filter" class="size-4" /> Filtres @if ($active)<span class="rounded-full bg-brand-800 px-1.5 text-xs leading-5 text-white">{{ $active }}</span>@endif
            </button>
        @endif
        @if ($active || ($search && filled(request($search))))
            <a href="{{ url()->current().($resetParams ? '?'.http_build_query($resetParams) : '') }}" class="shrink-0 px-1 text-sm link">Effacer</a>
        @endif
    </div>
    @if ($fields)
        <div x-cloak x-show="open" x-transition class="card mt-3 grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4">
            {{ $slot }}
            <button class="btn-primary col-span-2 sm:col-span-1 sm:self-end">Appliquer</button>
        </div>
    @endif
</form>
