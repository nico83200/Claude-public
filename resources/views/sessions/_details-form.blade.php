{{-- Informations d'une séance (page Modifier et page Préparer). --}}
<form method="POST" action="{{ route('sessions.update', $session) }}" class="grid gap-3 sm:grid-cols-2">
    @csrf @method('PUT')
    @isset($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endisset
    <x-field name="scheduled_at" type="datetime-local" label="Date et heure" :value="$session->scheduled_at->format('Y-m-d\TH:i')" required />
    <x-select name="session_type" label="Type" :options="\App\Models\RidingSession::typeOptions($session->session_type)" :value="$session->session_type" required />
    <x-select name="discipline" label="Discipline" :options="\App\Models\Horse::DISCIPLINES" :value="$session->discipline" placeholder="—" />
    <x-select name="rider_id" label="Cavalier" :options="$riders" :value="$session->rider_id" placeholder="—" />
    @if ($session->isLesson())<x-instructor-field :value="$session->instructor" />@endif
    <x-field name="objective" label="Objectif" :value="$session->objective" class="sm:col-span-2" />
    <x-field name="planned_minutes" type="number" label="Durée prévue" :value="$session->planned_minutes" />
    <x-field name="location" label="Lieu" :value="$session->location" />
    <x-field name="horse_state_before" label="État avant" :value="$session->horse_state_before" class="sm:col-span-2" />
    <x-textarea name="precautions" label="Précautions" :value="$session->precautions" class="sm:col-span-2" />
    <x-textarea name="notes" label="Notes" :value="$session->notes" class="sm:col-span-2" />
    <x-select name="status" label="Statut" :options="['planned' => 'Prévue', 'in_progress' => 'En cours', 'cancelled' => 'Annulée']" :value="$session->status" />
    <div class="flex items-end"><button class="btn-primary w-full">Enregistrer</button></div>
</form>
