{{-- Indicateur de connexion / synchronisation, piloté par resources/js/offline/status.js --}}
<a href="{{ route('offline.app') }}#pending" x-data="syncIndicator" class="btn-ghost gap-1.5 px-2 text-xs" :title="label" aria-live="polite">
    <span class="size-2.5 rounded-full" :class="dot"></span>
    <span class="hidden sm:inline" x-text="label">En ligne</span>
    <span x-cloak x-show="pending > 0" class="chip bg-amber-100 text-amber-800" x-text="pending"></span>
</a>
