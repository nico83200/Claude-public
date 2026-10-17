<x-layouts.app title="À valider">
    <x-page-header title="À valider" :subtitle="$horse ? $horse->shortName().' · rendez-vous et séances passés' : 'Rendez-vous et séances passés, tous les chevaux'" :back="route('dashboard')" />
    @if ($items->isEmpty())
        <x-empty title="Tout est à jour" icon="check" />
    @else
        <p class="mb-3 text-sm text-slate-600">Un rendez-vous de soin validé rejoint le carnet de santé et clôt le contrôle prévu. Les séries ne montrent que leurs 7 derniers jours ; au-delà de 30 jours, voir l'agenda.</p>
        <section class="card overflow-hidden" data-to-validate-page x-data="{ left: {{ $items->count() }} }" @swipe-validated="left = Math.max(0, left - 1)" @swipe-deleted="left = Math.max(0, left - 1)">
            <x-swipe.list hint-key="jc-hint-swipe-todo" hint="glissez vers la droite pour valider, vers la gauche pour supprimer." hint-class="mx-4 mt-3 md:hidden" class="divide-y divide-slate-100">
                @foreach ($items as $item)
                    @include('todo._row', ['item' => $item])
                @endforeach
            </x-swipe.list>
        </section>
    @endif
</x-layouts.app>
