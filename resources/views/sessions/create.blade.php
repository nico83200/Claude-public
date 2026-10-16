<x-layouts.app title="Nouvelle séance">
    <x-page-header title="Nouvelle séance" :back="route('sessions.index')" />
    @php $typeAsked = array_key_exists((string) request('type'), \App\Models\RidingSession::TYPES) ? request('type') : 'free'; @endphp
    <form method="POST" action="{{ route('sessions.store') }}" class="space-y-4" x-data="{ t: @js(old('session_type', $typeAsked)), noExercises: @js(\App\Models\RidingSession::WITHOUT_EXERCISES) }">
        @csrf
        <x-section>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="horse_id" label="Cheval" :options="$horses" :value="$selectedHorse" required placeholder="Choisir…" />
                <x-field name="scheduled_at" type="datetime-local" label="Date et heure" :value="($date ? (strlen($date) > 10 ? \Illuminate\Support\Carbon::parse($date) : \Illuminate\Support\Carbon::parse($date)->setTime(10, 0)) : now()->addHour()->startOfHour())->format('Y-m-d\TH:i')" required />
                @if ($templates->isNotEmpty())
                    <div class="sm:col-span-2">
                        <x-select name="template_id" label="Partir d'un modèle" :options="$templates" placeholder="Aucun (séance vierge)" />
                        <p class="mt-1 text-xs text-slate-500" x-text="noExercises.includes(t) ? 'Cours et balade : les exercices du modèle ne sont pas repris.' : 'Les exercices du modèle sont copiés ; le modèle n\'est pas modifié.'">Les exercices du modèle sont copiés ; le modèle n'est pas modifié.</p>
                    </div>
                @endif
                <div class="contents">
                    <x-select name="session_type" label="Type de séance" :options="\App\Models\RidingSession::TYPES" :value="$typeAsked" required x-model="t" />
                    <div x-show="t === 'private_lesson' || t === 'group_lesson'" x-cloak><x-instructor-field /></div>
                </div>
                <x-select name="discipline" label="Discipline" :options="\App\Models\Horse::DISCIPLINES" placeholder="—" />
                <x-field name="objective" label="Objectif" maxlength="255" class="sm:col-span-2" />
                <x-field name="planned_minutes" type="number" label="Durée prévue (min)" min="1" max="600" value="45" />
                <x-field name="location" label="Lieu" placeholder="Carrière, manège, extérieur…" />
                <x-field name="rider_name" label="Cavalier (si différent de vous, sans compte)" class="sm:col-span-2" />
                <x-field name="horse_state_before" label="État du cheval avant la séance" class="sm:col-span-2" />
                <x-textarea name="precautions" label="Précautions" class="sm:col-span-2" />
            </div>
        </x-section>
        <p x-show="noExercises.includes(t)" x-cloak class="text-sm text-slate-500" data-no-exercises>Cours et balade : pas de choix d'exercices à préparer. Après la séance, faites directement le bilan.</p>
        <button class="btn-primary" x-text="noExercises.includes(t) ? 'Créer la séance' : 'Créer et préparer les exercices'">Créer et préparer les exercices</button>
    </form>
</x-layouts.app>
