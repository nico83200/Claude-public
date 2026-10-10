<x-layouts.app title="Erreurs · Admin">
    <x-page-header title="Erreurs techniques récentes" subtitle="Extrait des journaux applicatifs (storage/logs)" />
    @include('admin._nav')
    @forelse ($entries as $e)
        <div class="mb-2 rounded-lg border border-slate-200 bg-white p-3 text-xs"><x-badge :color="in_array($e['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']) ? 'red' : 'amber'">{{ $e['level'] }}</x-badge> <span class="text-slate-500">{{ $e['date'] }} · {{ $e['file'] }}</span><p class="mt-1 font-mono break-all">{{ $e['message'] }}</p></div>
    @empty
        <x-empty title="Aucune erreur récente" icon="check" />
    @endforelse
</x-layouts.app>
