<x-layouts.app :title="$professional->fullName()">
    <x-page-header :title="$professional->fullName()" :subtitle="$professional->kindLabel().($professional->company ? ' · '.$professional->company : '')" :back="route('professionals.index')">
        @if ($canManage)
            <a href="{{ route('professionals.edit', $professional) }}" class="btn-secondary">Modifier</a>
            <form method="POST" action="{{ route('professionals.archive', $professional) }}">@csrf<button class="btn-ghost">{{ $professional->archived_at ? 'Réactiver' : 'Archiver' }}</button></form>
        @endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section title="Coordonnées">
            <div class="space-y-2 text-sm">
                @if ($professional->phone)
                    @php $tel = preg_replace('/[^0-9+]/', '', $professional->phone); @endphp
                    <p class="font-medium">{{ $professional->phone }}</p>
                    <div x-data x-show="$store.install.phone" x-cloak class="grid grid-cols-2 gap-2" data-phone-actions>
                        <a href="tel:{{ $tel }}" class="btn-primary justify-center"><x-icon name="phone" class="size-4" /> Appeler</a>
                        <a href="sms:{{ $tel }}" class="btn-secondary justify-center"><x-icon name="sms" class="size-4" /> SMS</a>
                    </div>
                @endif
                @if ($professional->email)<a href="mailto:{{ $professional->email }}" class="block link">{{ $professional->email }}</a>@endif
                @if ($professional->address)<p>{{ $professional->address }}</p>@endif
                @if ($shared)<p class="text-xs text-slate-500" data-shared-notice>Contact partagé par {{ $professional->organization->name }} avec un cheval partagé avec vous.</p>@endif
                @if ($professional->usual_rate && ! $shared)<p class="text-slate-500">Tarif habituel : {{ number_format((float) $professional->usual_rate, 2, ',', ' ') }} €</p>@endif
                @if ($professional->notes && ! $shared)<p class="whitespace-pre-line text-slate-600">{{ $professional->notes }}</p>@endif
            </div>
        </x-section>
        <x-section title="Chevaux suivis">
            @forelse ($horses as $h)<a href="{{ route('horses.show', $h) }}" class="block py-1 text-sm link">{{ $h->shortName() }}</a>@empty<p class="text-sm text-slate-500">Aucun cheval associé.</p>@endforelse
        </x-section>
        @if ($professional->kind === 'instructor' && ! $shared)
            {{-- Enseignant : ses tarifs, pour compter le coût de chaque cours fait (frais de cours, budget du propriétaire). --}}
            <x-section title="Tarifs" class="lg:col-span-3" id="tarifs" data-instructor-rates>
                @forelse ($professional->rates as $r)
                    <div class="flex items-center gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0" data-rate="{{ $r->kind }}">
                        <span class="min-w-0 flex-1">{{ $r->summary() }}</span>
                        @if ($canManage)
                            <form method="POST" action="{{ route('professionals.rates.destroy', [$professional, $r]) }}">@csrf @method('DELETE')<button class="btn-ghost min-h-10 px-2 text-sm text-red-600" aria-label="Supprimer ce tarif"><x-icon name="trash" class="size-4" /></button></form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aucun tarif : ajoutez-en pour que le coût de chaque cours avec {{ $professional->fullName() }} soit compté automatiquement, au nom de la personne qui paie.</p>
                @endforelse
                @if ($canManage)
                    <form method="POST" action="{{ route('professionals.rates.store', $professional) }}" class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-3 sm:grid-cols-4" x-data="{ kind: @js(old('kind', 'private_lesson')) }" data-rate-form>
                        @csrf
                        <x-select name="kind" label="Tarif" :options="\App\Models\InstructorRate::KINDS" value="private_lesson" x-model="kind" />
                        @foreach (\App\Models\InstructorRate::UNITS as $k => $units)
                            <div x-show="kind === @js($k)" @if ($k !== 'private_lesson') x-cloak @endif><x-select name="unit" :id="'rate_unit_'.$k" label="Unité" :options="$units" x-bind:disabled="kind !== {{ \Illuminate\Support\Js::from($k) }}" /></div>
                        @endforeach
                        <x-field name="amount" type="number" step="0.01" min="0" label="Montant (€)" required inputmode="decimal" />
                        <div x-show="kind === 'private_lesson' || kind === 'group_lesson'"><x-field name="duration_minutes" type="number" min="5" max="600" label="Durée (min)" help="Facultatif : tarif d'un cours de cette durée." /></div>
                        <div class="sm:col-span-4"><button class="btn-primary">Ajouter le tarif</button>
                            <p class="mt-2 text-xs text-slate-500">Cours collectif : prix par cavalier. Déplacement : compté une fois par jour de cours. Coaching : sur un concours de l'agenda dont {{ $professional->fullName() }} est l'intervenant, une fois le concours validé.</p></div>
                    </form>
                @endif
            </x-section>
        @endif
        <x-section title="Historique des interventions">
            @forelse ($interventions as $c)<a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0">{{ $c->performed_at->format('d/m/Y') }} — {{ $c->horse->shortName() }} · {{ $c->category->name }}</a>@empty<p class="text-sm text-slate-500">Aucune intervention visible.</p>@endforelse
        </x-section>
    </div>
</x-layouts.app>
