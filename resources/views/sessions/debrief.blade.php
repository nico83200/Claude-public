<x-layouts.app :title="'Bilan · '.$session->horse->shortName()">
    @php $lesson = in_array($session->session_type, ['private_lesson', 'group_lesson'], true); @endphp
    <x-page-header :title="$lesson ? 'Bilan du cours' : 'Bilan de séance'" :subtitle="$session->title().' · '.$session->horse->shortName().' · '.$session->scheduled_at->translatedFormat('j F à H:i')" :back="route('sessions.show', $session)" />
    @php $done = $session->exercises->where('status', 'done'); $work = $session->exercises->where('is_break', false); @endphp
    <form method="POST" action="{{ route('sessions.debrief.save', $session) }}" class="max-w-2xl space-y-4">
        @csrf @method('PUT')
        <x-section :title="$lesson ? 'Ce que vous avez travaillé' : 'Exercices réalisés'" x-data="{ added: [{ name: '', minutes: '' }] }">
            @if ($work->isNotEmpty())
                <input type="hidden" name="items_reviewed" value="1">
                <ul class="mb-4 divide-y divide-slate-100">
                    @foreach ($work as $item)
                        <li><label class="flex items-center gap-3 py-2.5">
                            <input type="checkbox" name="done[]" value="{{ $item->id }}" @checked($item->status !== 'skipped') class="size-5 rounded">
                            <span class="min-w-0 flex-1"><span class="block font-medium">{{ $item->name }}</span><span class="text-xs text-slate-500">{{ \App\Models\RidingSession::PHASES[$item->phase] ?? '' }}{{ $item->planned_minutes ? ' · '.$item->planned_minutes.' min' : '' }}</span></span>
                        </label></li>
                    @endforeach
                </ul>
                <p class="mb-2 text-sm font-medium text-slate-700">Autre chose ?</p>
            @else
                <p class="-mt-1 mb-3 text-sm text-slate-500">Notez les exercices du {{ $lesson ? 'cours' : 'jour' }} : ils alimentent le suivi de progression.</p>
            @endif
            <datalist id="library-exercises">@foreach ($library as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
            <div class="space-y-2">
                <template x-for="(row, i) in added" :key="i">
                    <div class="flex gap-2">
                        <input :name="'added[' + i + '][name]'" x-model="row.name" list="library-exercises" placeholder="Exercice (ex. Cercles de 20 m)" class="input flex-1" aria-label="Exercice"
                               @input="if (i === added.length - 1 && row.name.trim()) added.push({ name: '', minutes: '' })">
                        <input :name="'added[' + i + '][minutes]'" x-model="row.minutes" type="number" min="0" max="300" inputmode="numeric" placeholder="min" class="input w-20 shrink-0" aria-label="Minutes">
                    </div>
                </template>
            </div>
        </x-section>
        <x-section title="Comment ça s'est passé ?">
             <x-field name="actual_minutes" type="number" label="Durée réelle (min)" :value="$session->actual_minutes ?? ($done->sum('actual_minutes') ?: $session->planned_minutes)" min="0" max="600" />
            @foreach (['rider_feeling' => 'Ressenti du cavalier', 'horse_behavior' => 'Comportement du cheval', 'concentration' => 'Concentration', 'availability' => 'Disponibilité'] as $k => $label)
                <fieldset class="mt-4"><legend class="label">{{ $label }} <span class="font-normal text-slate-400">(1 = difficile, 5 = excellent)</span></legend>
                    <div class="grid grid-cols-5 gap-1">@for ($n = 1; $n <= 5; $n++)<label class="btn big-touch cursor-pointer border border-slate-300 has-checked:border-brand-600 has-checked:bg-brand-600 has-checked:text-white"><input type="radio" name="{{ $k }}" value="{{ $n }}" class="sr-only" @checked((int) old($k, $session->$k ?? 3) === $n)>{{ $n }}</label>@endfor</div>
                </fieldset>
            @endforeach
        </x-section>
        <x-section>
            <div class="space-y-3">
                <x-textarea name="progress" label="Progrès" :value="$session->progress" />
                <x-textarea name="difficulties" label="Difficultés" :value="$session->difficulties" />
                <x-textarea name="to_rework" label="Points à retravailler" :value="$session->to_rework" />
                <x-textarea name="anomalies" label="Anomalies constatées (le propriétaire / soignant sera prévenu)" :value="$session->anomalies" />
                <x-textarea name="next_objectives" label="Objectifs pour la prochaine séance" :value="$session->next_objectives" />
            </div>
        </x-section>
        <button class="btn-primary big-touch w-full sm:w-auto">Enregistrer et clôturer</button>
    </form>
</x-layouts.app>
