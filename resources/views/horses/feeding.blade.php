<x-layouts.app :title="'Alimentation · '.$horse->shortName()">
    <x-page-header :title="$horse->shortName()" subtitle="Alimentation" :back="route('horses.show', $horse)" />
    @include('horses._tabs')
    <div class="grid gap-4 lg:grid-cols-2">
        <x-section :title="$current ? 'Plan actuel : '.$current->name : 'Aucun plan alimentaire'">
            @if ($current)
                <p class="mb-2 text-xs text-slate-500">Depuis le {{ $current->starts_on->format('d/m/Y') }} · saisi par {{ $current->author?->name ?? '—' }}</p>
                <div class="table-wrap"><table class="table"><thead><tr><th>Heure</th><th>Aliment</th><th>Quantité</th><th>Fréquence</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @foreach ($current->entries as $e)<tr><td>{{ $e->time_of_day ? substr($e->time_of_day, 0, 5) : '—' }}</td><td>{{ $e->feed }} @if ($e->is_supplement)<x-badge color="purple">Complément</x-badge>@endif @if ($e->note)<span class="block text-xs text-slate-500">{{ $e->note }}</span>@endif</td><td>{{ $e->quantity ? rtrim(rtrim($e->quantity, '0'), '.').' '.$e->unit : '—' }}</td><td>{{ $e->frequency ?? '—' }}</td></tr>@endforeach
                </tbody></table></div>
                @if ($current->instructions)<p class="mt-3 text-sm whitespace-pre-line">{{ $current->instructions }}</p>@endif
            @else
                <p class="text-sm text-slate-500">Définissez la ration habituelle pour que tous les intervenants l'aient sous les yeux.</p>
            @endif
        </x-section>
        @if ($canEdit)
        <x-section title="{{ $current ? 'Nouveau plan (remplace l\'actuel)' : 'Créer le plan' }}">
            <form method="POST" action="{{ route('horses.feeding.store', $horse) }}" class="space-y-3" x-data="{ rows: {{ $current ? $current->entries->count() ?: 3 : 3 }} }">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="name" label="Nom du plan" :value="$current ? $current->name : 'Ration hiver'" required />
                    <x-field name="starts_on" type="date" label="À partir du" :value="now()->toDateString()" required />
                </div>
                <template x-for="i in rows" :key="i">
                    <div class="grid grid-cols-2 gap-2 rounded-lg border border-slate-200 p-2 sm:grid-cols-5">
                        <input :name="'entries[' + i + '][time_of_day]'" type="time" class="input py-2 text-sm" aria-label="Heure">
                        <input :name="'entries[' + i + '][feed]'" placeholder="Aliment" class="input py-2 text-sm sm:col-span-2" aria-label="Aliment">
                        <input :name="'entries[' + i + '][quantity]'" type="number" step="0.01" min="0" placeholder="Qté" class="input py-2 text-sm" aria-label="Quantité">
                        <select :name="'entries[' + i + '][unit]'" class="input py-2 text-sm" aria-label="Unité"><option>kg</option><option>g</option><option>L</option><option>litre(s)</option><option>mesure(s)</option><option>botte(s)</option></select>
                        <input :name="'entries[' + i + '][frequency]'" placeholder="Fréquence (ex. 2x/jour)" class="input py-2 text-sm sm:col-span-2" aria-label="Fréquence">
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" :name="'entries[' + i + '][is_supplement]'" value="1" class="rounded"> Complément</label>
                        <input :name="'entries[' + i + '][note]'" placeholder="Note" class="input py-2 text-sm sm:col-span-2" aria-label="Note">
                    </div>
                </template>
                <button type="button" class="btn-ghost text-sm" @click="rows++">+ Ligne</button>
                <x-textarea name="instructions" label="Consignes particulières" :value="$current?->instructions" />
                <button class="btn-primary">Enregistrer le plan</button>
            </form>
        </x-section>
        @endif
    </div>
    @if ($history->count() > 1)
    <x-section title="Historique des plans" class="mt-4">
        @foreach ($history as $p)
            <details class="border-t border-slate-100 py-2 text-sm first:border-0"><summary class="cursor-pointer"><span class="font-medium">{{ $p->name }}</span> — du {{ $p->starts_on->format('d/m/Y') }} {{ $p->ends_on ? 'au '.$p->ends_on->format('d/m/Y') : '(en cours)' }} · {{ $p->author?->name }}</summary>
                <ul class="mt-1 list-disc pl-6 text-slate-600">@foreach ($p->entries as $e)<li>{{ $e->time_of_day ? substr($e->time_of_day, 0, 5).' — ' : '' }}{{ $e->feed }} {{ $e->quantity ? rtrim(rtrim($e->quantity, '0'), '.').' '.$e->unit : '' }}</li>@endforeach</ul>
            </details>
        @endforeach
    </x-section>
    @endif
</x-layouts.app>
