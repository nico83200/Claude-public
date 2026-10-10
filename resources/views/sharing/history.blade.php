<x-layouts.app :title="'Historique · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Historique des accès et des modifications" :back="route('horses.sharing', $horse)" />
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Date</th><th>Action</th><th>Par</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr><td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</td><td>{{ __('audit.'.$log->action) !== 'audit.'.$log->action ? __('audit.'.$log->action) : $log->action }}</td><td class="text-sm">{{ $log->user?->name ?? 'Système' }}</td></tr>
                @empty <tr><td colspan="3" class="text-sm text-slate-500">Aucun événement.</td></tr> @endforelse
                </tbody>
            </table></div>
            <div class="mt-3">{{ $logs->links() }}</div>
        </div>
        <x-section title="Séances récemment modifiées">
            @foreach ($recentSessions as $s)<p class="border-t border-slate-100 py-1.5 text-sm first:border-0">{{ $s->scheduled_at->format('d/m') }} {{ $s->typeLabel() }} <span class="block text-xs text-slate-500">créée par {{ $s->creator?->name }} · modifiée {{ $s->updated_at->diffForHumans() }} {{ $s->trashed() ? '· supprimée' : '' }}</span></p>@endforeach
        </x-section>
    </div>
</x-layouts.app>
