<x-layouts.app title="Notifications">
    <x-page-header title="Notifications">
        <a href="{{ route('settings.notifications') }}" class="btn-ghost"><x-icon name="cog" class="size-4" /> Réglages</a>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-secondary">Tout marquer comme lu</button></form>
    </x-page-header>
    @forelse ($notifications as $n)
        <a href="{{ route('notifications.open', $n->id) }}" class="card mb-2 block p-4 {{ $n->read_at ? 'opacity-70' : 'border-brand-200' }}">
            <div class="flex items-start justify-between gap-2"><p class="min-w-0 font-semibold break-words">{{ $n->data['title'] ?? '' }}</p><span class="shrink-0 text-xs whitespace-nowrap text-slate-500">{{ $n->created_at->diffForHumans(short: true) }}</span></div>
            <p class="text-sm text-slate-600">{{ $n->data['body'] ?? '' }}</p>
        </a>
    @empty
        <x-empty title="Aucune notification" icon="bell" />
    @endforelse
    {{ $notifications->links() }}
</x-layouts.app>
