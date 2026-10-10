@php $e = $event ?? new \App\Models\CalendarEvent(['type' => 'custom', 'starts_at' => ($defaultDate ?? now())->copy()->setTime(9, 0), 'duration_minutes' => 60]); @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <x-select name="horse_id" label="Cheval" :options="$horses" :value="$e->horse_id ?? request('horse')" placeholder="Aucun (événement de l'espace)" />
    <x-select name="type" label="Type" :options="\App\Models\CalendarEvent::TYPES" :value="$e->type" required />
    <x-field name="title" label="Titre" :value="$e->title" required class="sm:col-span-2" />
    <x-field name="date" type="date" label="Date" :value="$e->starts_at->format('Y-m-d')" required />
    <x-field name="time" type="time" label="Heure" :value="$e->starts_at->format('H:i')" />
    <x-field name="duration_minutes" type="number" label="Durée (min)" :value="$e->duration_minutes" min="5" max="1440" />
    <div class="flex items-end"><x-checkbox name="all_day" label="Toute la journée" :checked="$e->all_day" /></div>
    <x-select name="professional_id" label="Intervenant" :options="$professionals" :value="$e->professional_id" placeholder="—" />
    <x-select name="recurrence" label="Récurrence" :options="['daily' => 'Tous les jours', 'weekly' => 'Toutes les semaines', 'monthly' => 'Tous les mois']" :value="$e->recurrence" placeholder="Aucune" />
    <x-field name="recurrence_interval" type="number" label="Tous les (intervalle)" :value="$e->recurrence_interval ?? 1" min="1" max="52" />
    <x-field name="recurrence_until" type="date" label="Jusqu'au" :value="$e->recurrence_until?->format('Y-m-d')" />
    <x-textarea name="notes" label="Observations" :value="$e->notes" class="sm:col-span-2" />
</div>
