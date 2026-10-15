@php
    // Nouvel événement : valeurs proposées par le lien d'origine (rappel intelligent « prendre rendez-vous »…).
    $prefillDate = rescue(fn () => request('date') ? \Illuminate\Support\Carbon::parse(request('date')) : null, null, false);
    $e = $event ?? new \App\Models\CalendarEvent([
        'type' => array_key_exists((string) request('type'), \App\Models\CalendarEvent::TYPES) ? request('type') : 'custom',
        'title' => mb_substr((string) request('title'), 0, 150) ?: null,
        'professional_id' => request()->integer('professional') ?: null,
        'starts_at' => ($prefillDate ?? $defaultDate ?? now())->copy()->setTime(9, 0), 'duration_minutes' => 60,
    ]);
@endphp
@php
    // Nouvel événement de type Séance ou Cours : on part directement sur la préparation de séance (cheval, date et heure repris).
    $toSession = ! isset($event) && app(\App\Services\HorseAccess::class)->horseIdsWith(auth()->user(), \App\Support\Perm::SESSIONS_CREATE) !== [];
@endphp
<div class="grid gap-3 sm:grid-cols-2" @if ($toSession) x-data @change="if ($event.target.name === 'type' && ['session', 'lesson'].includes($event.target.value)) { const f = $event.target.form; location.href = @js(route('sessions.create')) + '?' + new URLSearchParams({ horse: f.horse_id?.value || '', date: (f.date?.value || '') + (f.time?.value ? ' ' + f.time.value : ''), type: $event.target.value === 'lesson' ? 'private_lesson' : 'free' }); }" data-session-redirect @endif>
    <x-select name="horse_id" label="Cheval" :options="$horses" :value="$e->horse_id ?? request('horse')" placeholder="Aucun (événement de l'espace)" />
    <x-select name="type" label="Type" :options="\App\Models\CalendarEvent::TYPES" :value="$e->type" required :help="$toSession ? 'Séance ou Cours : la préparation de séance s\'ouvre (exercices, cavalier, bilan).' : null" />
    <x-field name="title" label="Titre" :value="$e->title" required class="sm:col-span-2" />
    <x-field name="date" type="date" label="Date" :value="$e->starts_at->format('Y-m-d')" required />
    <x-field name="time" type="time" label="Heure" :value="$e->starts_at->format('H:i')" />
    <x-field name="duration_minutes" type="number" label="Durée (min)" :value="$e->duration_minutes" min="5" max="1440" />
    <div class="flex items-end"><x-checkbox name="all_day" label="Toute la journée" :checked="$e->all_day" /></div>
    <x-professional-select :options="$professionals" :value="$e->professional_id" />
    <x-select name="recurrence" label="Récurrence" :options="['daily' => 'Tous les jours', 'weekly' => 'Toutes les semaines', 'monthly' => 'Tous les mois']" :value="$e->recurrence" placeholder="Aucune" />
    <x-field name="recurrence_interval" type="number" label="Tous les (intervalle)" :value="$e->recurrence_interval ?? 1" min="1" max="52" />
    <x-field name="recurrence_until" type="date" label="Jusqu'au" :value="$e->recurrence_until?->format('Y-m-d')" />
    <x-textarea name="notes" label="Observations" :value="$e->notes" class="sm:col-span-2" />
</div>
