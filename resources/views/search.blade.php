<x-layouts.app title="Recherche">
    <x-page-header title="Recherche" :subtitle="$q ? '« '.$q.' »' : null" />
    @if (mb_strlen($q) < 2)
        <p class="text-sm text-slate-500">Saisissez au moins 2 caractères.</p>
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
