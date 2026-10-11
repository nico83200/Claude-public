<x-layouts.app :title="'Alimentation · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="grid gap-4 lg:grid-cols-2" x-data="{ editing: {{ $current || ! $canEdit ? 'false' : 'true' }} }">
        @if ($current)
        <x-section :title="$current->name">
            @if ($canEdit)
            <x-slot:headerActions><button type="button" class="btn-secondary min-h-9 px-4 text-sm" @click="editing = true; $nextTick(() => document.getElementById('feeding-form').scrollIntoView({ behavior: 'smooth' }))" x-show="! editing"><x-icon name="pencil" class="size-4" /> Modifier</button></x-slot:headerActions>
            @endif
            <p class="-mt-1 mb-3 text-xs text-slate-500">Depuis le {{ $current->starts_on->translatedFormat('j F Y') }} · {{ $current->author?->name ?? '—' }}</p>
            <ul class="divide-y divide-slate-100">
                @foreach ($current->entries as $e)
                    <li class="flex items-start gap-3 py-3">
                        <span class="w-12 shrink-0 text-sm font-semibold text-slate-900 tabular-nums">{{ $e->time_of_day ? substr($e->time_of_day, 0, 5) : '—' }}</span>
                        <span class="min-w-0 flex-1"><span class="font-medium">{{ $e->feed }}</span> @if ($e->is_supplement)<x-badge color="purple">Complément</x-badge>@endif
                            @if ($e->frequency || $e->note)<span class="block text-xs text-slate-500">{{ collect([$e->frequency, $e->note])->filter()->implode(' · ') }}</span>@endif</span>
                        <span class="shrink-0 text-sm font-semibold whitespace-nowrap">{{ $e->quantity ? rtrim(rtrim($e->quantity, '0'), '.').' '.$e->unit : '' }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($current->instructions)<p class="mt-3 rounded-2xl bg-brand-50 p-3 text-sm whitespace-pre-line text-brand-900">{{ $current->instructions }}</p>@endif
        </x-section>
        @elseif (! $canEdit)
            <x-empty title="Aucun plan alimentaire" icon="food">La ration habituelle n'a pas encore été renseignée.</x-empty>
        @endif
        @if ($canEdit)
        <x-section id="feeding-form" :title="$current ? 'Nouvelle ration' : 'Ration habituelle'" x-show="editing" x-cloak>
            <p class="-mt-1 mb-4 text-sm text-slate-500">{{ $current ? 'Elle remplacera la ration actuelle, qui reste dans l\'historique.' : 'Tous les intervenants l\'auront sous les yeux.' }}</p>
            <form method="POST" action="{{ route('horses.feeding.store', $horse) }}" class="space-y-4" x-data="{ rows: {{ $current ? $current->entries->count() ?: 2 : 2 }} }">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="name" label="Nom" :value="$current ? $current->name : 'Ration hiver'" required />
                    <x-field name="starts_on" type="date" label="À partir du" :value="now()->toDateString()" required />
                </div>
                <div class="space-y-3">
                    <template x-for="i in rows" :key="i">
                        <div class="space-y-2 rounded-2xl bg-slate-50 p-3">
                            <input :name="'entries[' + i + '][feed]'" placeholder="Aliment (foin, granulés…)" class="input" aria-label="Aliment">
                            <div class="grid grid-cols-[1fr_1fr_1.25fr] gap-2">
                                <input :name="'entries[' + i + '][quantity]'" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Qté" class="input" aria-label="Quantité">
                                <select :name="'entries[' + i + '][unit]'" class="input" aria-label="Unité"><option>kg</option><option>g</option><option>L</option><option>litre(s)</option><option>mesure(s)</option><option>botte(s)</option></select>
                                <input :name="'entries[' + i + '][time_of_day]'" type="time" class="input" aria-label="Heure">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input :name="'entries[' + i + '][frequency]'" placeholder="Fréquence" class="input" aria-label="Fréquence (ex. 2x/jour)">
                                <input :name="'entries[' + i + '][note]'" placeholder="Note" class="input" aria-label="Note">
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" :name="'entries[' + i + '][is_supplement]'" value="1" class="size-4 rounded"> Complément alimentaire</label>
                        </div>
                    </template>
                </div>
                <button type="button" class="btn-secondary w-full border-dashed" @click="rows++"><x-icon name="plus" class="size-4" /> Ajouter un aliment</button>
                <x-textarea name="instructions" label="Consignes particulières" :value="$current?->instructions" rows="2" />
                <div class="flex gap-2">
                    <button class="btn-primary flex-1">Enregistrer</button>
                    @if ($current)<button type="button" class="btn-ghost" @click="editing = false">Annuler</button>@endif
                </div>
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
