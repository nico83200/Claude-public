{{-- Formulaire d'une ration (création ou modification). $plan : ration modifiée, ou null. --}}
@php
    $entries = $plan ? $plan->entries->map(fn ($e) => ['feed' => $e->feed, 'quantity' => $e->quantity !== null ? rtrim(rtrim((string) $e->quantity, '0'), '.') : '', 'unit' => $e->unit, 'time_of_day' => $e->time_of_day ? substr($e->time_of_day, 0, 5) : '', 'frequency' => $e->frequency, 'note' => $e->note, 'is_supplement' => (bool) $e->is_supplement])->values()->all() : [];
    $entries = $entries ?: [['feed' => '', 'quantity' => '', 'unit' => 'kg', 'time_of_day' => '', 'frequency' => '', 'note' => '', 'is_supplement' => false]];
    $mode = $plan?->ends_on ? 'date' : 'none';
    $units = ['kg', 'g', 'L', 'litre(s)', 'mesure(s)', 'botte(s)'];
    $fid = 'r'.($plan?->id ?? 'new').'_'; // identifiants distincts : plusieurs formulaires sur la page
@endphp
<form method="POST" action="{{ $plan ? route('horses.feeding.update', [$horse, $plan]) : route('horses.feeding.store', $horse) }}" class="space-y-4"
      x-data="{ rows: @js($entries), mode: @js($mode) }" data-feeding-form>
    @csrf @if ($plan) @method('PUT') @endif
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field :id="$fid.'name'" name="name" label="Nom de la ration" :value="$plan?->name ?? ''" placeholder="Ration du matin, complément 15 jours…" required />
        <x-field :id="$fid.'starts'" name="starts_on" type="date" label="À partir du" :value="($plan?->starts_on ?? today())->toDateString()" required />
    </div>
    <fieldset>
        <legend class="label">Fin</legend>
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach (['none' => 'Sans fin', 'date' => 'À une date', 'duration' => 'Pour une durée'] as $k => $l)
                <label class="flex min-h-10 cursor-pointer items-center gap-2 rounded-full border border-slate-200 px-3" :class="mode === @js($k) && 'border-brand-500 bg-brand-50'"><input type="radio" name="end_mode" value="{{ $k }}" x-model="mode" class="size-4"> {{ $l }}</label>
            @endforeach
        </div>
        <div class="mt-2" x-show="mode === 'date'" x-cloak><x-field :id="$fid.'ends'" name="ends_on" type="date" label="Dernier jour" :value="$plan?->ends_on?->toDateString()" /></div>
        <div class="mt-2 grid grid-cols-2 gap-2" x-show="mode === 'duration'" x-cloak>
            <x-field :id="$fid.'dur'" name="duration_value" type="number" label="Durée" min="1" max="365" inputmode="numeric" placeholder="15" />
            <x-select :id="$fid.'unit'" name="duration_unit" label="Unité" :options="['days' => 'jours', 'weeks' => 'semaines', 'months' => 'mois']" value="days" />
        </div>
        <p class="mt-1 text-xs text-slate-500" x-show="mode !== 'none'" x-cloak>À la fin de la période, la ration est archivée automatiquement et vous êtes prévenu.</p>
    </fieldset>
    <div class="space-y-3">
        <template x-for="(row, i) in rows" :key="i">
            <div class="space-y-2 rounded-2xl bg-slate-50 p-3">
                <div class="flex gap-2">
                    <input :name="'entries[' + i + '][feed]'" x-model="row.feed" placeholder="Aliment (foin, granulés…)" class="input" aria-label="Aliment">
                    <button type="button" class="btn-ghost size-11 shrink-0 px-0 text-slate-400" @click="rows.splice(i, 1); if (! rows.length) rows.push({ feed: '', unit: 'kg' })" aria-label="Retirer cet aliment"><x-icon name="x" class="size-5" /></button>
                </div>
                <div class="grid grid-cols-[1fr_1fr_1.25fr] gap-2">
                    <input :name="'entries[' + i + '][quantity]'" x-model="row.quantity" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Qté" class="input" aria-label="Quantité">
                    <select :name="'entries[' + i + '][unit]'" x-model="row.unit" class="input" aria-label="Unité">@foreach ($units as $u)<option>{{ $u }}</option>@endforeach</select>
                    <input :name="'entries[' + i + '][time_of_day]'" x-model="row.time_of_day" type="time" class="input" aria-label="Heure">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input :name="'entries[' + i + '][frequency]'" x-model="row.frequency" placeholder="Fréquence" class="input" aria-label="Fréquence (ex. 2x/jour)">
                    <input :name="'entries[' + i + '][note]'" x-model="row.note" placeholder="Note" class="input" aria-label="Note">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" :name="'entries[' + i + '][is_supplement]'" value="1" x-model="row.is_supplement" class="size-4 rounded"> Complément alimentaire</label>
            </div>
        </template>
    </div>
    <button type="button" class="btn-secondary w-full border-dashed" @click="rows.push({ feed: '', quantity: '', unit: 'kg', time_of_day: '', frequency: '', note: '', is_supplement: false })"><x-icon name="plus" class="size-4" /> Ajouter un aliment</button>
    <x-textarea :id="$fid.'instr'" name="instructions" label="Consignes particulières" :value="$plan?->instructions" rows="2" />
    <div class="flex gap-2">
        <button class="btn-primary flex-1">{{ $plan ? 'Enregistrer les modifications' : 'Ajouter la ration' }}</button>
        <button type="button" class="btn-ghost" @click="$dispatch('feeding-close')">Annuler</button>
    </div>
</form>
