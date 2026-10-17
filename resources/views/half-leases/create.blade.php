<x-layouts.app :title="'Demi-pension · '.$horse->shortName()">
    <x-page-header title="Nouvelle demi-pension" :subtitle="$horse->shortName().' · la personne accepte la proposition, puis son accès s\'ouvre'" :back="route('horses.sharing', $horse)" />
    <form method="POST" action="{{ route('horses.half-leases.store', $horse) }}" class="max-w-2xl space-y-4" data-half-lease-form>
        @csrf
        @include('half-leases._form')
        <button class="btn-primary min-h-12 w-full justify-center">Envoyer la proposition</button>
    </form>
</x-layouts.app>
