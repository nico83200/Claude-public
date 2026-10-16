<x-layouts.app :title="'Modifier · '.$event->title">
    <x-page-header title="Modifier l'événement" :subtitle="$event->title" :back="route('calendar.show', $event)" />
    <form method="POST" action="{{ route('calendar.update', $event) }}" class="max-w-3xl space-y-4">
        @csrf @method('PUT')
        <x-section>
            @include('calendar._form', ['horses' => $event->horse ? collect([$event->horse_id => $event->horse->shortName()]) : collect(), 'event' => $event])
            <div class="mt-3 max-w-xs"><x-select name="status" label="Statut" :options="\App\Models\CalendarEvent::STATUSES" :value="$event->status" /></div>
            @if ($event->careRecord)
                <p class="mt-3 text-xs text-slate-500">Le soin correspondant du carnet de santé sera mis à jour (date, intervenant).</p>
            @elseif (in_array($event->type, ['farrier', 'veterinary', 'care'], true))
                <p class="mt-3 text-xs text-slate-500">Marqué « Réalisé », ce rendez-vous sera ajouté au carnet de santé du cheval.</p>
            @endif
            @php $siblings = $event->groupSiblings(); @endphp
            @if ($siblings->isNotEmpty())
                <label class="mt-4 flex items-start gap-3 rounded-2xl bg-brand-50 p-3 text-sm text-brand-900" data-whole-group>
                    <input type="checkbox" name="whole_group" value="1" checked class="mt-0.5 size-5 rounded border-slate-300 text-brand-700">
                    <span>Rendez-vous groupé : appliquer aussi la date, l'heure et l'intervenant à {{ $siblings->map(fn ($e) => $e->horse?->shortName())->filter()->implode(', ') }}.</span>
                </label>
            @endif
        </x-section>
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
