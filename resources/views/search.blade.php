<x-layouts.app title="Recherche">
    <x-page-header title="Recherche" />
    {{-- Champ toujours visible (sur téléphone, celui de l'en-tête est replié derrière la loupe). --}}
    <form method="GET" action="{{ route('search') }}" class="mb-5 flex gap-2" role="search" data-search-page>
        <div class="relative flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $q }}" @if (mb_strlen($q) < 2) autofocus @endif placeholder="Nom d'un cheval, d'un soin, d'un intervenant…" class="input min-h-12 pl-11" aria-label="Rechercher">
        </div>
        <button class="btn-primary min-h-12 shrink-0 px-4">Rechercher</button>
    </form>
    @if (mb_strlen($q) < 2)
        <p class="text-sm text-slate-500">Tapez au moins 2 lettres : un cheval, un soin, un intervenant, une séance…</p>
    @elseif (empty($results))
        <x-empty title="Aucun résultat" icon="search" />
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($results as $group => $items)
                <x-section :title="$group">
                    @foreach ($items as $r)<a href="{{ $r['url'] }}" class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0 hover:bg-slate-50"><span class="font-medium">{{ $r['title'] }}</span><span class="text-xs text-slate-500">{{ $r['meta'] }}</span></a>@endforeach
                </x-section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
