<x-layouts.app title="Appareils">
    <x-page-header title="Paramètres" />
    @include('settings._nav')
    <x-section title="Appareils utilisant le mode hors ligne" class="max-w-3xl">
        <p class="mb-3 text-sm text-slate-600">Une purge efface les données locales de l'appareil dès sa prochaine connexion. Un appareil resté hors ligne conserve sa copie jusqu'à sa reconnexion ou l'expiration des données ({{ config('equine.offline.ttl_hours') }} h).</p>
        @forelse ($devices as $d)
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-3 text-sm first:border-0">
                <span><span class="font-medium">{{ \Illuminate\Support\Str::limit($d->label ?? $d->user_agent ?? 'Appareil', 70) }}</span><span class="block text-xs text-slate-500">Dernière activité : {{ $d->last_seen_at?->format('d/m/Y H:i') ?? '—' }} · dernière synchronisation : {{ $d->last_pull_at?->format('d/m/Y H:i') ?? '—' }}</span></span>
                @if ($d->wipe_requested_at)<x-badge color="amber">Purge en attente</x-badge>@else<form method="POST" action="{{ route('settings.devices.wipe', $d) }}" onsubmit="return confirm('Effacer les données locales de cet appareil à sa prochaine connexion ?')">@csrf<button class="btn-ghost text-red-600">Purger</button></form>@endif
            </div>
        @empty <p class="text-sm text-slate-500">Aucun appareil.</p> @endforelse
    </x-section>
</x-layouts.app>
