<x-layouts.app :title="'Demi-pension · '.$horse->shortName()">
    <x-page-header :title="'Demi-pension de '.$lease->riderName()" :subtitle="$horse->shortName()" :back="route('half-leases.show', $lease)" />
    <form method="POST" action="{{ route('half-leases.update', $lease) }}" class="max-w-2xl space-y-4" data-half-lease-form>
        @csrf @method('PUT')
        @include('half-leases._form')
        <button class="btn-primary min-h-12 w-full justify-center">Enregistrer</button>
        @if ($lease->status === 'active')<p class="text-center text-xs text-slate-500">{{ $lease->riderName() }} sera prévenue des changements.</p>@endif
    </form>
</x-layouts.app>
