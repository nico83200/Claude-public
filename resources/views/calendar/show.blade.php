<x-layouts.app :title="$event->title">
    <x-page-header :title="$event->title" :subtitle="\App\Models\CalendarEvent::TYPES[$event->type].' · '.$event->starts_at->translatedFormat('l j F Y').($event->all_day ? '' : ' à '.$event->starts_at->format('H:i'))" :back="route('calendar.index', ['date' => $event->starts_at->toDateString()])">
        @if ($canEdit)<x-confirm-delete :action="route('calendar.destroy', $event)" message="Supprimer cet événement (et toutes ses occurrences) ?" />@endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section>
            <x-dl :items="[
                'Cheval' => $event->horse?->shortName(),
                'Statut' => \App\Models\CalendarEvent::STATUSES[$event->status],
                'Durée' => $event->all_day ? 'Toute la journée' : $event->duration_minutes.' min',
                'Intervenant' => $event->professional?->fullName(),
                'Responsable' => $event->responsible?->name,
                'Récurrence' => $event->recurrence ? ['daily' => 'Quotidienne', 'weekly' => 'Hebdomadaire', 'monthly' => 'Mensuelle'][$event->recurrence].($event->recurrence_until ? ' jusqu\'au '.$event->recurrence_until->format('d/m/Y') : '') : null,
                'Observations' => $event->notes,
            ]" />
            @if ($event->source_type)<p class="mt-3 text-xs text-slate-500">Événement généré automatiquement à partir d'un soin ou d'un traitement.</p>@endif
        </x-section>
        @if ($canEdit)
        <x-section title="Modifier" class="lg:col-span-2">
            <form method="POST" action="{{ route('calendar.update', $event) }}" class="space-y-4">
                @csrf @method('PUT')
                @include('calendar._form', ['horses' => $event->horse ? collect([$event->horse_id => $event->horse->official_name]) : collect(), 'event' => $event])
                <x-select name="status" label="Statut" :options="\App\Models\CalendarEvent::STATUSES" :value="$event->status" />
                <button class="btn-primary">Enregistrer</button>
            </form>
        </x-section>
        @endif
    </div>
</x-layouts.app>
