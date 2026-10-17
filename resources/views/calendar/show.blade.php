@php $when = $occurrence ?? $event->starts_at; @endphp
<x-layouts.app :title="$event->title">
    <x-page-header :title="$event->title" :subtitle="\App\Models\CalendarEvent::TYPES[$event->type].' · '.$when->translatedFormat('l j F Y').($event->all_day ? '' : ' à '.$when->format('H:i')).($occurrence ? ' · série' : '')" :back="route('calendar.index', ['date' => $when->toDateString()])">
        @if ($canEdit)
            <a href="{{ route('calendar.edit', $event) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Modifier{{ $occurrence ? ' la série' : '' }}</a>
            @if ($deleteChoices)
                {{-- Occurrence d'une série : cette occurrence, celle-ci et les suivantes, ou toute la série. --}}
                <div x-data="{ open: false }" class="inline" data-occurrence-delete>
                    <button type="button" class="btn-ghost text-red-600" @click="open = true">Supprimer</button>
                    <template x-teleport="body">
                        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[80] flex items-end justify-center bg-slate-900/45 sm:items-center" @click.self="open = false" @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="Que supprimer ?">
                            <div class="w-full max-w-md rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl">
                                <h2 class="mb-3 text-lg font-semibold text-slate-900">Que supprimer ?</h2>
                                <div class="space-y-2">
                                    @foreach ($deleteChoices as $choice)
                                        <form method="POST" action="{{ $choice['url'] }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="flex min-h-14 w-full flex-col items-start justify-center rounded-2xl bg-slate-50 px-4 py-2 text-left hover:bg-red-50" data-delete-choice="{{ $choice['scope'] }}">
                                                <span class="font-medium {{ $choice['scope'] === 'series' ? 'text-red-700' : 'text-slate-900' }}">{{ $choice['label'] }}</span>
                                                <span class="text-xs text-slate-500">{{ $choice['help'] }}</span>
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                                <button type="button" class="btn-ghost mt-3 min-h-11 w-full justify-center" @click="open = false">Annuler</button>
                            </div>
                        </div>
                    </template>
                </div>
            @else
                <x-confirm-delete :action="route('calendar.destroy', $event)" :message="$event->careRecord ? 'Supprimer cet événement ? Le soin correspondant sera aussi retiré du carnet de santé.' : ($event->recurrence ? 'Supprimer toute la série (toutes ses occurrences) ?' : 'Supprimer cet événement ?')" />
            @endif
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
                'Statut' => $event->timeStatus($occurrence)->label,
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
            @if ($canMarkDone && ! $canValidate)
                <form method="POST" action="{{ route('calendar.done', array_filter([$event, 'date' => $occurrence?->toDateString()])) }}" class="mt-4" data-mark-done>
                    @csrf
                    <button type="submit" class="btn-primary w-full"><x-icon name="check" class="size-4" /> {{ $occurrence ? 'Valider cette occurrence' : 'Marquer comme réalisé' }}</button>
                </form>
                @if ($occurrence)<p class="mt-1 text-xs text-slate-500">Seule l'occurrence du {{ $occurrence->format('d/m') }} est validée ; la série continue.</p>@endif
            @endif
            @if ($canValidate)
                <a href="{{ route('horses.care.create', [$event->horse, 'evenement' => $event->id]) }}" class="btn-primary mt-4 w-full"><x-icon name="check" class="size-4" /> Valider l'intervention</a>
                <p class="mt-1 text-xs text-slate-500">Enregistre le soin réalisé et propose la date du prochain passage.</p>
            @endif
        </x-section>
        @if ($vaccines)
            @include('calendar._competition-vaccines')
        @endif
        @if ($coaching->isNotEmpty())
            <x-section title="Coaching" data-event-coaching-cost>
                @foreach ($coaching as $c)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="min-w-0 flex-1"><span class="block font-medium {{ $c->trashed() ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $c->label }}</span><span class="block text-xs text-slate-500">À la charge de {{ $c->payer_id === auth()->id() ? 'vous' : $c->payer?->name }}</span></span>
                        <span class="font-semibold">{{ number_format((float) $c->amount, 2, ',', ' ') }} €</span>
                        @unless ($c->trashed())<x-badge :color="$c->paid_on ? 'green' : 'amber'">{{ $c->paid_on ? 'Payé' : 'À payer' }}</x-badge>@endunless
                    </div>
                @endforeach
                <a href="{{ route('lesson-costs.index') }}" class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm link">Tous mes frais de cours <x-icon name="chevron" class="size-4" /></a>
            </x-section>
        @endif
        @if ($event->careRecord)
            <x-section title="Carnet de santé">
                <p class="text-sm text-slate-600">Ce rendez-vous est enregistré comme soin réalisé{{ $event->careRecord->category ? ' (« '.$event->careRecord->category->name.' »)' : '' }} : il apparaît dans la santé de {{ $event->horse?->shortName() }} et se met à jour avec l'événement.</p>
                <a href="{{ route('horses.care.show', [$event->horse, $event->careRecord]) }}" class="btn-secondary mt-3 w-full"><x-icon name="health" class="size-4" /> Voir ou compléter le soin</a>
            </x-section>
        @endif
    </div>
</x-layouts.app>
