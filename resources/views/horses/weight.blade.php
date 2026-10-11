<x-layouts.app :title="'Poids · '.$horse->shortName()">
    <x-horse-header :horse="$horse" />
    @php $kg = fn ($v) => number_format((float) $v, $v == (int) $v ? 0 : 1, ',', ' ').' kg'; @endphp
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Chiffres clés --}}
            <div class="card grid grid-cols-3 divide-x divide-slate-100 py-4 text-center">
                <div class="px-2"><p class="text-xs text-slate-500">Dernier poids</p><p class="mt-1 text-xl font-bold sm:text-2xl">{{ $last ? $kg($last->weight_kg) : '—' }}</p><p class="text-xs text-slate-500">{{ $last?->measured_on->translatedFormat('j M Y') }}</p></div>
                <div class="px-2"><p class="text-xs text-slate-500">Évolution</p>
                    @if ($change)
                        <p class="mt-1 text-xl font-bold sm:text-2xl {{ abs($change['pct']) >= \App\Http\Controllers\MeasurementController::ALERT_PERCENT ? 'text-amber-700' : '' }}">{{ $change['kg'] > 0 ? '+' : '' }}{{ number_format($change['kg'], 1, ',', ' ') }} kg</p>
                        <p class="text-xs text-slate-500">{{ $change['pct'] > 0 ? '+' : '' }}{{ number_format($change['pct'], 1, ',', ' ') }} % depuis la mesure précédente</p>
                    @else<p class="mt-1 text-xl font-bold">—</p>@endif
                </div>
                <div class="px-2"><p class="text-xs text-slate-500">État corporel</p><p class="mt-1 text-xl font-bold sm:text-2xl">{{ $lastCondition ? $lastCondition->body_condition.'/9' : '—' }}</p><p class="text-xs text-slate-500">{{ $lastCondition ? \Illuminate\Support\Str::after(\App\Models\HorseMeasurement::BODY_CONDITION[$lastCondition->body_condition], '· ') : '' }}</p></div>
            </div>

            {{-- Courbe --}}
            <x-section title="Courbe de poids">
                @if ($points->count() >= 1)
                    <div x-data="weightChart(@js($points))" class="relative" x-ref="box" @pointermove="move($event)" @pointerleave="leave()" @touchmove.passive="move($event)">
                        <svg x-ref="svg" class="weight-chart block w-full" role="img" aria-label="Évolution du poids de {{ $horse->shortName() }}"></svg>
                        <div x-show="tip" x-cloak class="wc-tip pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-xl px-3 py-2 text-xs shadow-lg" :style="tip && `left:${tip.left}px;top:${tip.top - 10}px`">
                            <p class="font-semibold" x-text="tip?.value"></p><p class="opacity-80" x-text="tip ? tip.title + ' · ' + tip.method : ''"></p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Points creux : poids estimé (ruban ou à l'œil). Une variation de plus de {{ (int) \App\Http\Controllers\MeasurementController::ALERT_PERCENT }} % entre deux mesures prévient les responsables du cheval.</p>
                @else
                    <x-empty title="Aucune mesure pour le moment" icon="scale">Pesez votre cheval ou estimez son poids au ruban : la courbe apparaîtra ici.</x-empty>
                @endif
            </x-section>

            {{-- Tableau (vue accessible des valeurs) --}}
            @if ($measures->isNotEmpty())
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Date</th><th>Poids</th><th>État</th><th>Méthode</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($measures as $m)
                        <tr>
                            <td class="whitespace-nowrap">{{ $m->measured_on->format('d/m/Y') }}<span class="block text-xs text-slate-500">{{ $m->author?->name }}</span></td>
                            <td class="whitespace-nowrap font-medium">{{ $m->weight_kg ? $kg($m->weight_kg) : '—' }}</td>
                            <td>{{ $m->body_condition ? $m->body_condition.'/9' : '—' }}</td>
                            <td class="text-xs text-slate-500">{{ \App\Models\HorseMeasurement::METHODS[$m->method] ?? $m->method }}@if ($m->girth_cm)<br>{{ $m->girth_cm }} × {{ $m->length_cm }} cm @endif @if ($m->notes)<br>{{ $m->notes }}@endif</td>
                            <td>@if ($canCreate && $m->author_id === auth()->id())<x-confirm-delete :action="route('horses.weight.destroy', [$horse, $m])" message="Supprimer cette mesure ?" />@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        @if ($canCreate)
            <x-section title="Nouvelle mesure">
                <form method="POST" action="{{ route('horses.weight.store', $horse) }}" class="space-y-3" x-data="{ method: @js(old('method', 'scale')), girth: @js(old('girth_cm')), length: @js(old('length_cm')) }">
                    @csrf
                    <x-field name="measured_on" type="date" label="Date" :value="now()->toDateString()" required />
                    <x-select name="method" label="Méthode" :options="\App\Models\HorseMeasurement::METHODS" value="scale" required x-model="method" />
                    <div x-show="method !== 'tape'"><x-field name="weight_kg" type="number" step="0.5" min="30" max="1500" label="Poids (kg)" inputmode="decimal" /></div>
                    <div x-show="method === 'tape'" x-cloak class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="girth_cm" type="number" label="Tour de poitrine (cm)" min="60" max="300" inputmode="numeric" x-model="girth" />
                            <x-field name="length_cm" type="number" label="Longueur (cm)" min="50" max="300" inputmode="numeric" x-model="length" />
                        </div>
                        <p class="rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900" x-show="girth > 0 && length > 0">Poids estimé : <strong x-text="Math.round(girth * girth * length / 11877) + ' kg'"></strong></p>
                        @if (is_file(public_path('videos/mesure-ruban.mp4')))
                            <button type="button" class="inline-flex items-center gap-1.5 text-sm link" onclick="document.getElementById('tape-video').showModal(); document.querySelector('#tape-video video')?.play()"><x-icon name="play" class="size-4" /> Comment mesurer au ruban (1 min)</button>
                        @endif
                    </div>
                    <x-select name="body_condition" label="État corporel (facultatif)" :options="\App\Models\HorseMeasurement::BODY_CONDITION" placeholder="—" />
                    <x-field name="notes" label="Remarque" maxlength="500" placeholder="Après la tonte, nouveau foin…" />
                    <button class="btn-primary w-full">Enregistrer</button>
                </form>
            </x-section>
        @endif
    </div>

    @if ($canCreate && is_file(public_path('videos/mesure-ruban.mp4')))
        <dialog id="tape-video" class="m-auto w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-3xl bg-black p-0 backdrop:bg-slate-900/70" onclose="this.querySelector('video').pause()">
            <video src="/videos/mesure-ruban.mp4" poster="/videos/mesure-ruban.jpg" controls playsinline preload="none" class="block w-full">
                <track kind="subtitles" src="/videos/mesure-ruban.vtt" srclang="fr" label="Français" default>
            </video>
            <form method="dialog" class="flex justify-end bg-slate-900 p-2"><button class="btn-ghost text-sm text-white">Fermer</button></form>
        </dialog>
    @endif
</x-layouts.app>
