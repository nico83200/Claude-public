<x-layouts.app :title="'Ordonnance · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    <div class="mb-4 rounded-2xl bg-brand-50 p-4 text-sm text-brand-900" data-prescription-review>
        <strong>Vérifiez avant d'enregistrer.</strong> L'assistant a lu le document{{ $d['practitioner'] ? ' de '.$d['practitioner'] : '' }} ;
        la posologie est recopiée telle qu'écrite. Confiance de lecture : {{ ['high' => 'bonne', 'medium' => 'moyenne', 'low' => 'faible — relisez attentivement'][$d['confidence']] ?? $d['confidence'] }}.
    </div>
    @if ($otherHorse)
        <div class="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900" data-other-horse>Le document mentionne <strong>{{ $otherHorse }}</strong>, et non {{ $horse->shortName() }}. Vérifiez qu'il s'agit du bon cheval.</div>
    @endif
    <form method="POST" action="{{ route('horses.prescription.save', [$horse, $token]) }}" class="grid gap-4 lg:grid-cols-2">
        @csrf
        <x-section title="Soin">
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <x-select name="care_category_id" label="Type de soin" :options="$categories" :value="$categoryId" required />
                    <x-field name="performed_at" type="date" label="Date de la visite" :value="$date" required :max="today()->toDateString()" />
                </div>
                <x-select name="professional_id" label="Praticien" :options="$professionals" :value="$professionalId" placeholder="—" />
                @if ($d['practitioner'] && ! $professionalId)<p class="-mt-2 text-xs text-slate-500">Lu sur le document : {{ $d['practitioner'] }} (absent de vos intervenants).</p>@endif
                <x-field name="reason" label="Motif" :value="$d['reason']" maxlength="255" />
                <x-textarea name="diagnosis" label="Diagnostic communiqué" rows="2" :value="$d['diagnosis']" />
                <x-textarea name="instructions" label="Consignes" rows="3" :value="$d['instructions']" />
                <x-field name="next_check_on" type="date" label="Prochain contrôle" :value="$nextCheck" help="Ajouté à l'agenda avec un rappel." />
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="keep_document" value="0"><input type="checkbox" name="keep_document" value="1" checked> Joindre la photo de l'ordonnance au soin</label>
            </div>
        </x-section>
        <x-section title="Traitements prescrits">
            @forelse ($treatments as $i => $t)
                @php $start = $date; $end = $t['duration_days'] > 0 ? \Illuminate\Support\Carbon::parse($date)->addDays($t['duration_days'] - 1)->toDateString() : null; @endphp
                <div class="border-t border-slate-100 py-3 first:border-0 first:pt-0" x-data="{ on: true }" data-prescribed-treatment>
                    <label class="mb-2 flex items-center gap-2 font-medium"><input type="hidden" name="treatments[{{ $i }}][create]" value="0"><input type="checkbox" name="treatments[{{ $i }}][create]" value="1" x-model="on" checked> Créer ce traitement</label>
                    <div class="grid grid-cols-2 gap-3" x-show="on">
                        <x-field :name="'treatments['.$i.'][product]'" label="Produit" :value="$t['product']" class="col-span-2" maxlength="150" />
                        <x-field :name="'treatments['.$i.'][dosage]'" label="Posologie (telle qu'écrite)" :value="$t['dosage']" class="col-span-2" maxlength="255" />
                        <x-field :name="'treatments['.$i.'][frequency]'" label="Fréquence" :value="$t['frequency']" maxlength="255" />
                        <x-field :name="'treatments['.$i.'][times_per_day]'" type="number" min="1" max="24" label="Prises par jour" :value="$t['times_per_day'] ?: null" />
                        <x-field :name="'treatments['.$i.'][starts_on]'" type="date" label="Début" :value="$start" />
                        <x-field :name="'treatments['.$i.'][ends_on]'" type="date" label="Fin" :value="$end" />
                        <x-field :name="'treatments['.$i.'][instructions]'" label="Voie / précisions" :value="$t['route']" class="col-span-2" maxlength="2000" />
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucun médicament lu sur le document. Vous pourrez ajouter un traitement depuis la page Santé.</p>
            @endforelse
        </x-section>
        <div class="flex flex-wrap gap-2 lg:col-span-2">
            <button class="btn-primary" data-prescription-save><x-icon name="check" class="size-4" /> Enregistrer le soin{{ $treatments->isNotEmpty() ? ' et les traitements' : '' }}</button>
        </div>
    </form>
    <form method="POST" action="{{ route('horses.prescription.cancel', [$horse, $token]) }}" class="mt-2">@csrf @method('DELETE')<button class="btn-ghost text-sm">Abandonner</button></form>
</x-layouts.app>
