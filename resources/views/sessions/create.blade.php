<x-layouts.app title="Nouvelle séance">
    <x-page-header title="Nouvelle séance" :back="route('sessions.index')" />
    <form method="POST" action="{{ route('sessions.store') }}" class="space-y-4">
        @csrf
        <x-section>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="horse_id" label="Cheval" :options="$horses" :value="$selectedHorse" required placeholder="Choisir…" />
                <x-field name="scheduled_at" type="datetime-local" label="Date et heure" :value="($date ? \Illuminate\Support\Carbon::parse($date)->setTime(10, 0) : now()->addHour()->startOfHour())->format('Y-m-d\TH:i')" required />
                @if ($templates->isNotEmpty())
                    <x-select name="template_id" label="Partir d'un modèle" :options="$templates" placeholder="Aucun (séance vierge)" class="sm:col-span-2" help="Les exercices du modèle sont copiés ; le modèle n'est pas modifié." />
                @endif
                <x-select name="session_type" label="Type de séance" :options="\App\Models\RidingSession::TYPES" value="free" required />
                <x-select name="discipline" label="Discipline" :options="\App\Models\Horse::DISCIPLINES" placeholder="—" />
                <x-field name="objective" label="Objectif" maxlength="255" class="sm:col-span-2" />
                <x-field name="planned_minutes" type="number" label="Durée prévue (min)" min="1" max="600" value="45" />
                <x-field name="location" label="Lieu" placeholder="Carrière, manège, extérieur…" />
                <x-field name="rider_name" label="Cavalier (si différent de vous, sans compte)" class="sm:col-span-2" />
                <x-field name="horse_state_before" label="État du cheval avant la séance" class="sm:col-span-2" />
                <x-textarea name="precautions" label="Précautions" class="sm:col-span-2" />
            </div>
        </x-section>
        <button class="btn-primary">Créer et préparer les exercices</button>
    </form>
</x-layouts.app>
