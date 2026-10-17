<x-layouts.app :title="$record->exists ? 'Modifier le soin' : 'Nouveau soin'">
    <x-page-header :title="$record->exists ? 'Modifier le soin' : 'Nouveau soin'" :subtitle="$horse->shortName()" :back="route('horses.care.index', $horse)" />
    <form method="POST" action="{{ $record->exists ? route('horses.care.update', [$horse, $record]) : route('horses.care.store', $horse) }}" enctype="multipart/form-data" class="space-y-4"
        x-data="careNext({ rules: @js($rules), usualPros: @js($usualPros), cat: @js((string) old('care_category_id', $record->care_category_id)), performed: @js(old('performed_at', $record->performed_at?->format('Y-m-d\TH:i'))),
            mode: @js(old('next_mode', $defaultMode)), value: @js(old('next_interval_value')), unit: @js(old('next_interval_unit', 'weeks')), fromOld: @js(old('next_interval_value') !== null),
            cost: @js((string) old('cost', $record->cost)), addExpense: @js((bool) old('add_expense', filled(old('cost', $record->cost)) || ! $record->exists)) })">
        @csrf @if ($record->exists) @method('PUT') <input type="hidden" name="version" value="{{ $record->version }}"> @endif
        @if ($event)<input type="hidden" name="calendar_event_id" value="{{ $event->id }}"><p class="rounded-lg bg-gold-50 p-3 text-sm text-slate-700">Validation de l'intervention prévue le {{ $event->starts_at->format('d/m/Y') }} : « {{ $event->title }} ». Elle sera marquée réalisée dans le calendrier.</p>@endif
        <x-section>
            <div class="grid gap-4 sm:grid-cols-2">
                <div x-data="{ rhythms: @js((object) $rhythms) }">
                    <x-select name="care_category_id" label="Catégorie" :options="$categories" :value="$record->care_category_id" required placeholder="Choisir…" x-model="cat" />
                    <p x-show="rhythms[cat]" x-cloak x-text="rhythms[cat]" class="mt-1.5 flex gap-1.5 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600" data-rhythm-info></p>
                </div>
                @php
                    $checkedVaccines = old('vaccines', $record->vaccines ?? \App\Support\Vaccines::infer($record->reason, $record->care_performed, $record->observations));
                @endphp
                {{-- Vaccination : vaccins reçus (vérifiés avant un concours). --}}
                <div x-show="@js($vaccinationIds).includes(String(cat))" x-cloak class="sm:col-span-2" data-care-vaccines>
                    <p class="label">Vaccins reçus</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Support\Vaccines::TYPES as $vk => $vl)
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-sand-200 bg-white px-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-900">
                                <input type="checkbox" name="vaccines[]" value="{{ $vk }}" class="size-4 rounded" @checked(in_array($vk, (array) $checkedVaccines, true))> {{ $vl }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Utile avant un concours (grippe, rhinopneumonie). Rien de coché : les vaccins sont reconnus dans le motif, par exemple « Rappel grippe-tétanos ».</p>
                    @error('vaccines.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <x-field name="performed_at" type="datetime-local" label="Date et heure" :value="$record->performed_at?->format('Y-m-d\TH:i')" required x-model="performed" />
                <x-professional-select label="Professionnel" :options="$professionals" :value="$record->professional_id" :org="$horse->organization" help="Absent de la liste ? Ajoutez-le dans Intervenants." />
                <x-field name="reason" label="Motif" :value="$record->reason" />
                <x-field name="cost" type="number" step="0.01" min="0" label="Coût (€)" :value="$record->cost" x-model="cost" inputmode="decimal" />
            </div>
            {{-- Détails repliés : pour un parage ou un vermifuge, l'essentiel suffit. --}}
            @php $hasDetails = filled($record->observations) || filled($record->care_performed) || filled($record->diagnosis) || filled($record->instructions) || $errors->hasAny(['observations', 'care_performed', 'diagnosis', 'instructions']); @endphp
            <details class="group mt-4 rounded-2xl bg-slate-50 p-4" @if ($hasDetails) open @endif data-care-details>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-medium text-slate-700">Ajouter des détails (observations, soins, diagnostic, consignes)<x-icon name="chevron" class="size-4 transition group-open:rotate-90" /></summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <x-textarea name="observations" label="Observations" :value="$record->observations" rows="2" />
                    <x-textarea name="care_performed" label="Soins réalisés" :value="$record->care_performed" rows="2" />
                    <x-textarea name="diagnosis" label="Diagnostic communiqué par le professionnel" :value="$record->diagnosis" rows="2" />
                    <x-textarea name="instructions" label="Consignes" :value="$record->instructions" rows="2" />
                </div>
            </details>
        </x-section>
        <x-section title="Prochain passage">
            <div class="space-y-3">
                <div class="flex flex-wrap gap-2 text-sm" role="radiogroup" aria-label="Prochain passage">
                    @foreach (['interval' => 'Dans un délai', 'date' => 'À une date précise', 'none' => 'Pas de suivi'] as $m => $ml)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border px-3" :class="mode === '{{ $m }}' ? 'border-brand-600 bg-brand-50 text-brand-900' : 'border-sand-200 bg-white'">
                            <input type="radio" name="next_mode" value="{{ $m }}" x-model="mode" class="sr-only"> {{ $ml }}
                        </label>
                    @endforeach
                </div>
                <div x-show="mode === 'interval'" class="flex flex-wrap items-end gap-2">
                    <div class="w-24"><label class="label" for="niv">Dans</label><input id="niv" type="number" name="next_interval_value" min="1" max="120" x-model="value" :disabled="mode !== 'interval'" class="input"></div>
                    <div class="w-36"><label class="label sr-only" for="niu">Unité</label><select id="niu" name="next_interval_unit" x-model="unit" :disabled="mode !== 'interval'" class="input"><option value="weeks">semaines</option><option value="months">mois</option></select></div>
                    <p class="pb-3 text-sm text-slate-600" x-show="nextDate">soit le <strong x-text="nextDate"></strong></p>
                </div>
                <div x-show="mode === 'date'"><x-field name="next_check_on" type="date" label="Date du prochain contrôle" :value="$record->next_check_on?->format('Y-m-d')" x-bind:disabled="mode !== 'date'" /></div>
                <div x-show="mode === 'interval'"><x-checkbox name="remember_frequency" label="Retenir cette fréquence pour ce cheval" help="Active le rappel de suivi de cette catégorie (modifiable dans Santé › Rappels de suivi)." :checked="true" /></div>
                <p x-show="mode !== 'none'" class="text-xs text-slate-500">Le prochain contrôle est indiqué dans Santé (vous pourrez l'ajouter à l'agenda) et une notification est envoyée avant l'échéance.</p>
                @error('next_interval_value')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </x-section>
        <x-section>
            @if ($linkedExpense)
                <p class="flex items-center gap-2 text-sm text-slate-600" data-linked-expense><x-icon name="check" class="size-4 text-emerald-600" /> Dépense liée ({{ number_format((float) $linkedExpense->amount, 2, ',', ' ') }} €) : sa date, son montant et l'intervenant suivent ce soin.</p>
            @elseif ($canExpense)
                {{-- Cochée automatiquement dès qu'un montant est saisi. --}}
                <label class="flex items-start gap-3 text-sm" data-add-expense>
                    <input type="hidden" name="add_expense" value="0">
                    <input type="checkbox" name="add_expense" value="1" class="mt-0.5 size-5 rounded" x-model="addExpense">
                    <span class="font-medium text-slate-900">Ajouter le coût aux dépenses du cheval</span>
                </label>
            @endif
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="att">Joindre un document (compte rendu, ordonnance, facture)</label><input id="att" type="file" name="attachment" class="text-sm">@error('attachment')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <x-select name="attachment_category" label="Type de document" :options="$docCategories" value="report" />
            </div>
        </x-section>
        <button class="btn-primary w-full sm:w-auto">Enregistrer le soin</button>
    </form>
</x-layouts.app>
