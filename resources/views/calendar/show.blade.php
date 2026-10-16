<x-layouts.app :title="$event->title">
    <x-page-header :title="$event->title" :subtitle="\App\Models\CalendarEvent::TYPES[$event->type].' · '.$event->starts_at->translatedFormat('l j F Y').($event->all_day ? '' : ' à '.$event->starts_at->format('H:i'))" :back="route('calendar.index', ['date' => $event->starts_at->toDateString()])">
        @if ($canEdit)
            <a href="{{ route('calendar.edit', $event) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Modifier</a>
            <x-confirm-delete :action="route('calendar.destroy', $event)" :message="$event->careRecord ? 'Supprimer cet événement ? Le soin correspondant sera aussi retiré du carnet de santé.' : 'Supprimer cet événement (et toutes ses occurrences) ?'" />
        @endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-section class="lg:col-span-2" data-event-details>
            @if ($siblings->isNotEmpty())
                <div class="mb-4 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900" data-event-group>
                    <p class="font-medium">Rendez-vous groupé</p>
                    <p class="mt-0.5">Même passage pour {{ $event->horse?->shortName() }} et
                        @foreach ($siblings as $sib)<a href="{{ route('calendar.show', $sib) }}" class="link">{{ $sib->horse?->shortName() }}</a>{{ $loop->remaining > 1 ? ', ' : ($loop->remaining === 1 ? ' et ' : '') }}@endforeach.</p>
                </div>
            @endif
            @if ($event->professional)
                <x-professional-contact :professional="$event->professional" class="mb-4"
                    :sms="'Bonjour, c\'est au sujet du rendez-vous du '.$event->starts_at->translatedFormat('l j F').($event->all_day ? '' : ' à '.$event->starts_at->format('H\hi')).($event->horse ? ' pour '.$event->horse->shortName() : '').'. '" />
            @endif
            <x-dl :items="[
                'Cheval' => $event->horse?->shortName(),
                'Statut' => \App\Models\CalendarEvent::STATUSES[$event->status],
                'Durée' => $event->all_day ? 'Toute la journée' : $event->duration_minutes.' min',
                'Responsable' => $event->responsible?->name,
                'Récurrence' => $event->recurrence ? ['daily' => 'Quotidienne', 'weekly' => 'Hebdomadaire', 'monthly' => 'Mensuelle'][$event->recurrence].($event->recurrence_until ? ' jusqu\'au '.$event->recurrence_until->format('d/m/Y') : '') : null,
                'Observations' => $event->notes,
            ]" />
            @if ($event->source_type === (new \App\Models\CareRecord)->getMorphClass())
                <p class="mt-3 text-xs text-slate-500">Contrôle prévu à la suite d'un soin : le déplacer ici change la date du contrôle ; le marquer « Réalisé » enregistre le soin dans le carnet de santé.</p>
                @if ($event->horse)<a href="{{ route('horses.care.show', [$event->horse, $event->source_id]) }}" class="mt-2 inline-block text-sm font-medium text-brand-700 hover:underline">Voir le soin d'origine</a>@endif
            @elseif ($event->source_type === (new \App\Models\Treatment)->getMorphClass() || $event->treatment)
                <p class="mt-3 text-xs text-slate-500">{{ $event->treatment ? 'Début du traitement' : 'Fin prévue du traitement' }} : il apparaît aussi dans la santé du cheval et se met à jour avec cet événement.</p>
                @if ($event->horse)<a href="{{ route('horses.care.index', $event->horse) }}" class="mt-2 inline-block text-sm font-medium text-brand-700 hover:underline">Voir les traitements</a>@endif
            @endif
            @if ($canValidate)
                <a href="{{ route('horses.care.create', [$event->horse, 'evenement' => $event->id]) }}" class="btn-primary mt-4 w-full"><x-icon name="check" class="size-4" /> Valider l'intervention</a>
                <p class="mt-1 text-xs text-slate-500">Enregistre le soin réalisé et propose la date du prochain passage.</p>
            @endif
        </x-section>
        @if ($event->careRecord)
            <x-section title="Carnet de santé">
                <p class="text-sm text-slate-600">Ce rendez-vous est enregistré comme soin réalisé{{ $event->careRecord->category ? ' (« '.$event->careRecord->category->name.' »)' : '' }} : il apparaît dans la santé de {{ $event->horse?->shortName() }} et se met à jour avec l'événement.</p>
                <a href="{{ route('horses.care.show', [$event->horse, $event->careRecord]) }}" class="btn-secondary mt-3 w-full"><x-icon name="health" class="size-4" /> Voir ou compléter le soin</a>
            </x-section>
        @endif
    </div>
</x-layouts.app>
