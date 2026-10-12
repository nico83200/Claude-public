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
                @if ($professional->usual_rate)<p class="text-slate-500">Tarif habituel : {{ number_format((float) $professional->usual_rate, 2, ',', ' ') }} €</p>@endif
                @if ($professional->notes)<p class="whitespace-pre-line text-slate-600">{{ $professional->notes }}</p>@endif
            </div>
        </x-section>
        <x-section title="Chevaux suivis">
            @forelse ($horses as $h)<a href="{{ route('horses.show', $h) }}" class="block py-1 text-sm link">{{ $h->shortName() }}</a>@empty<p class="text-sm text-slate-500">Aucun cheval associé.</p>@endforelse
        </x-section>
        <x-section title="Historique des interventions">
            @forelse ($interventions as $c)<a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0">{{ $c->performed_at->format('d/m/Y') }} — {{ $c->horse->shortName() }} · {{ $c->category->name }}</a>@empty<p class="text-sm text-slate-500">Aucune intervention visible.</p>@endforelse
        </x-section>
    </div>
</x-layouts.app>
