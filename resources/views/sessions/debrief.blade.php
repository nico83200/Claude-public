<x-layouts.app :title="'Bilan · '.$session->horse->shortName()">
    <x-page-header title="Bilan de séance" :subtitle="$session->horse->shortName().' · '.$session->scheduled_at->format('d/m/Y H:i')" :back="route('sessions.show', $session)" />
    @php $done = $session->exercises->where('status', 'done'); @endphp
    <p class="mb-4 text-sm text-slate-600">{{ $done->count() }} exercice(s) réalisé(s) sur {{ $session->exercises->count() }}.</p>
    <form method="POST" action="{{ route('sessions.debrief.save', $session) }}" class="max-w-2xl space-y-4">
        @csrf @method('PUT')
        <x-section>
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
