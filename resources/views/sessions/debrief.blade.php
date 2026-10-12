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
        {{-- Questions à choix, adaptées aux exercices et à l'objectif de la séance (réponses enregistrées dans le bilan). --}}
        @php
            $has = fn (?string $text, string $choice) => $text && str_contains(mb_strtolower($text), mb_strtolower($choice));
            $pick = fn (?string $text, array $choices) => array_values(array_filter($choices, fn ($c) => $has($text, $c)));
            $choices = array_values(array_unique(array_merge($questions['exercises'], $questions['aspects'])));
            $chip = 'flex min-h-11 cursor-pointer items-center rounded-full border border-slate-300 px-4 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-600 has-[:checked]:text-white';
        @endphp
        <input type="hidden" name="questionnaire" value="1">
        <div data-debrief-questions x-data="{ hard: @js($pick($session->difficulties, $choices)), rework: @js($pick($session->to_rework, $choices)), anomalies: @js($pick($session->anomalies, \App\Services\DebriefQuestions::ANOMALIES) ?: ['Rien à signaler']), comment: false }" x-init="let prev = [...hard]; $watch('hard', (v) => { v.filter((x) => ! prev.includes(x) && x !== 'Rien de particulier').forEach((x) => { if (! rework.includes(x)) rework.push(x) }); prev = [...v]; })">
        <x-section title="Votre bilan en quelques touches">
            <div class="space-y-5">
                @if ($questions['objective'])
                    <fieldset data-q="objective"><legend class="label">L'objectif « {{ $questions['objective'] }} » est-il atteint ?</legend>
                        <div class="flex flex-wrap gap-2">@foreach (['atteint' => 'Atteint', 'en partie' => 'En partie', 'pas encore' => 'Pas encore'] as $v => $l)<label class="{{ $chip }}"><input type="radio" name="q_objective" value="{{ $v }}" class="sr-only" @checked($has($session->progress, '» : '.$v))>{{ $l }}</label>@endforeach</div>
                    </fieldset>
                @endif
                <fieldset data-q="good"><legend class="label">Qu'est-ce qui a bien marché ?</legend>
                    <div class="flex flex-wrap gap-2">@foreach ($choices as $c)<label class="{{ $chip }}"><input type="checkbox" name="q_good[]" value="{{ $c }}" class="sr-only" @checked($has(\Illuminate\Support\Str::after((string) $session->progress, 'Bien réussi :'), $c) && str_contains((string) $session->progress, 'Bien réussi'))>{{ $c }}</label>@endforeach</div>
                </fieldset>
                <fieldset data-q="hard"><legend class="label">Qu'est-ce qui a été difficile ?</legend>
                    <div class="flex flex-wrap gap-2">@foreach (array_merge(['Rien de particulier'], $choices) as $c)<label class="{{ $chip }}"><input type="checkbox" name="q_hard[]" value="{{ $c }}" x-model="hard" class="sr-only">{{ $c }}</label>@endforeach</div>
                </fieldset>
                <fieldset data-q="rework"><legend class="label">À retravailler</legend>
                    <div class="flex flex-wrap gap-2">@foreach ($choices as $c)<label class="{{ $chip }}"><input type="checkbox" name="q_rework[]" value="{{ $c }}" x-model="rework" class="sr-only">{{ $c }}</label>@endforeach</div>
                </fieldset>
                <fieldset data-q="anomalies"><legend class="label">Quelque chose d'anormal chez le cheval ? <span class="font-normal text-slate-400">(le propriétaire / soignant sera prévenu)</span></legend>
                    <div class="flex flex-wrap gap-2">@foreach (array_merge(['Rien à signaler'], \App\Services\DebriefQuestions::ANOMALIES) as $c)<label class="{{ $chip }}"><input type="checkbox" name="q_anomalies[]" value="{{ $c }}" x-model="anomalies" class="sr-only" @change="if ($event.target.checked) anomalies = $event.target.value === 'Rien à signaler' ? ['Rien à signaler'] : anomalies.filter((a) => a !== 'Rien à signaler')">{{ $c }}</label>@endforeach</div>
                </fieldset>
                <fieldset data-q="next"><legend class="label">Pour la prochaine séance</legend>
                    <div class="flex flex-wrap gap-2">@foreach (array_merge(array_map(fn ($c) => 'Travailler : '.$c, array_slice($questions['aspects'], 0, 4)), \App\Services\DebriefQuestions::NEXT) as $c)<label class="{{ $chip }}"><input type="checkbox" name="q_next[]" value="{{ $c }}" class="sr-only" @checked($has($session->next_objectives, $c))>{{ $c }}</label>@endforeach</div>
                </fieldset>
                <div>
                    <button type="button" class="text-sm font-medium text-brand-700 underline" x-show="! comment" @click="comment = true">+ Ajouter une précision (facultatif)</button>
                    <div x-show="comment" x-cloak><label class="label" for="q_comment">Précision</label><input id="q_comment" name="q_comment" maxlength="500" class="input" placeholder="En une phrase, si besoin"></div>
                </div>
            </div>
        </x-section>
        </div>
        <button class="btn-primary big-touch w-full sm:w-auto">Enregistrer et clôturer</button>
    </form>
</x-layouts.app>
