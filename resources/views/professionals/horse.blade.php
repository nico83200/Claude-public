<x-layouts.app :title="'Intervenants · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Intervenants" :back="route('horses.show', $horse)" />
    @include('horses._tabs')
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-2 lg:col-span-2">
            @forelse ($attached as $p)
                <div class="card flex items-center justify-between p-4">
                    <a href="{{ route('professionals.show', $p) }}"><span class="font-semibold">{{ $p->fullName() }}</span> @if ($p->pivot->is_primary)<x-badge color="brand">Référent</x-badge>@endif<span class="block text-sm text-slate-500">{{ $p->kindLabel() }} · {{ $p->phone }}</span></a>
                    <div class="flex gap-1">
                        @if ($p->phone)<a href="tel:{{ $p->phone }}" class="btn-secondary" aria-label="Appeler">📞</a>@endif
                        @if ($canEdit)<x-confirm-delete :action="route('horses.professionals.detach', [$horse, $p])" label="Retirer" message="Retirer cet intervenant de la fiche ?" />@endif
                    </div>
                </div>
            @empty
                <x-empty title="Aucun intervenant associé" icon="users" />
            @endforelse
        </div>
        @if ($canEdit)
        <x-section title="Associer un intervenant">
            @if ($available->isEmpty())
                <p class="text-sm text-slate-500">Le répertoire de l'espace {{ $horse->organization->name }} ne contient pas d'autre intervenant.</p>
            @else
            <form method="POST" action="{{ route('horses.professionals.attach', $horse) }}" class="space-y-3">
                @csrf
                <x-select name="professional_id" label="Intervenant" :options="$available->mapWithKeys(fn ($p) => [$p->id => $p->fullName().' – '.$p->kindLabel()])" required />
                <x-checkbox name="is_primary" label="Référent pour ce cheval" />
                <button class="btn-primary w-full">Associer</button>
            </form>
            @endif
            <a href="{{ route('professionals.create') }}" class="mt-3 block text-sm link">Créer une fiche intervenant</a>
        </x-section>
        @endif
    </div>
</x-layouts.app>
