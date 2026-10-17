{{-- Accueil : rendez-vous et séances passés à valider (les premiers ; « Voir tout » ouvre la liste complète). --}}
@if ($toValidate->isNotEmpty())
    @php $shown = 3; @endphp
    <section class="card mb-5 overflow-hidden" data-to-validate x-data="{ left: {{ $toValidate->count() }} }" @swipe-validated="left = Math.max(0, left - 1)" @swipe-deleted="left = Math.max(0, left - 1)">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5 sm:px-6">
            <h2 class="flex items-center gap-2 text-[17px] font-semibold text-slate-900">À valider <span class="rounded-full bg-amber-100 px-2 text-sm font-semibold text-amber-800" x-text="left">{{ $toValidate->count() }}</span></h2>
            @if ($toValidate->count() > $shown)<a href="{{ route('todo.index') }}" class="link text-sm" data-to-validate-all>Voir tout</a>@endif
        </div>
        <p class="px-5 pt-1 text-sm text-slate-500 sm:px-6" x-show="left > 0">C'est passé : validez, ou indiquez que ça n'a pas eu lieu, pour garder le carnet et les échéances justes.</p>
        <p class="px-5 pt-1 text-sm text-brand-700 sm:px-6" x-show="left === 0" x-cloak>Tout est à jour.</p>
        <x-swipe.list hint-key="jc-hint-swipe-todo" hint="glissez vers la droite pour valider, vers la gauche pour supprimer." hint-class="mx-4 mt-3 md:hidden" class="mt-2 divide-y divide-slate-100 border-t border-slate-100">
            @foreach ($toValidate->take($shown) as $item)
                @include('todo._row', ['item' => $item])
            @endforeach
        </x-swipe.list>
    </section>
@endif
