<x-layouts.app :title="'Santé · '.$horse->shortName()">
    <x-horse-header :horse="$horse">
        @if ($canHealth)<a href="{{ route('horses.vet-summary', $horse) }}" class="btn-secondary" data-vet-summary-link><x-icon name="share" class="size-4" /> Résumé pour le vétérinaire</a>@endif
        @if ($canHealth)<a href="{{ route('horses.export.health', $horse) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> Export PDF <x-locked-mark :org="$horse->organization" feature="exports" /></a>@endif
        @if ($canEdit)<a href="{{ route('horses.care.create', $horse) }}" class="btn-primary"><x-icon name="plus" /> Soin</a>@endif
    
    </x-horse-header>

    @if ($canEdit)@include('health._prescription-card')@endif
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($rhythms->isNotEmpty())
            <x-section title="Rythme habituel">
                <p class="-mt-1 mb-2 text-xs text-slate-500">Calculé à partir de l'historique des soins : l'écart habituel entre deux passages et la date probable du prochain.</p>
                <div class="divide-y divide-slate-100" data-rhythms>
                    @foreach ($rhythms as $r)
                        <div class="py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="min-w-0"><span class="font-medium">{{ $r['category']->name }}</span>
                                    <span class="block text-xs text-slate-500">Toutes les {{ $r['label'] }} en moyenne ({{ $r['count'] }} passages) · dernier le {{ $r['last']->format('d/m/Y') }}</span></span>
                                @if ($r['planned'])<x-badge color="green">Rendez-vous le {{ $r['planned']->starts_at->format('d/m') }}</x-badge>
                                @elseif ($r['due_in'] < 0)<x-badge color="red">Prévu vers le {{ $r['next']->format('d/m') }} · en retard</x-badge>
                                @else<x-badge :color="$r['due_in'] <= 14 ? 'amber' : 'slate'">Prochain vers le {{ $r['next']->format('d/m/Y') }}</x-badge>@endif
                            </div>
                            @if ($canEdit && (! $r['planned'] || \App\Services\CareRhythm::differsFromRule($r)))
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @unless ($r['planned'])
                                        <a href="{{ route('calendar.index', ['nouveau' => 1, 'horse' => $horse->id, 'type' => $r['appointment_type'], 'title' => $r['category']->name, 'date' => $r['next']->isPast() ? today()->addDay()->toDateString() : $r['next']->toDateString(), 'professional' => $r['last_professional_id']]) }}" class="btn-secondary min-h-9 px-4 text-sm"><x-icon name="calendar" class="size-4" /> Prendre rendez-vous</a>
                                    @endunless
                                    @if (\App\Services\CareRhythm::differsFromRule($r))
                                        <form method="POST" action="{{ route('horses.care.rhythm', $horse) }}">@csrf<input type="hidden" name="category_id" value="{{ $r['category']->id }}"><button class="btn-ghost min-h-9 px-3 text-sm">Régler le rappel sur {{ $r['label'] }}</button></form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-section>
            @endif
            @if ($canHealth && $reminderRules->isNotEmpty())
            <x-section title="Rappels de suivi" x-data="{ edit: false }">
                <x-slot:headerActions>@if ($canEdit)<button type="button" @click="edit = ! edit" class="btn-ghost text-sm" x-text="edit ? 'Fermer' : 'Paramétrer'">Paramétrer</button>@endif</x-slot:headerActions>
                <div x-show="! edit" class="divide-y divide-slate-100">
                    @foreach ($reminderRules as $rule)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <span><span class="font-medium">{{ $rule['category']->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $rule['enabled'] ? 'Tous les '.\App\Services\CareReminders::describe($rule['interval_value'], $rule['interval_unit']).' · rappel '.$rule['notice_days'].' j avant' : 'Rappel désactivé' }}{{ $rule['last'] ? ' · dernier passage le '.$rule['last']->format('d/m/Y') : ' · aucun passage enregistré' }}</span></span>
                            @if ($rule['enabled'] && $rule['next'])<x-badge :color="$rule['next']->isPast() ? 'red' : 'amber'">Prochain : {{ $rule['next']->format('d/m/Y') }}</x-badge>
                            @elseif ($rule['enabled'] && $rule['last'])<x-badge color="slate">Pas d'échéance</x-badge>
                            @elseif (! $rule['enabled'])<x-badge>Inactif</x-badge>@endif
                        </div>
                    @endforeach
                </div>
                @if ($canEdit)
                <form x-show="edit" x-cloak method="POST" action="{{ route('horses.care.reminders', $horse) }}" class="space-y-3">
                    @csrf @method('PUT')
                    @foreach ($reminderRules as $catId => $rule)
                        <fieldset class="grid grid-cols-2 items-end gap-2 rounded-xl border border-sand-200 p-3 sm:grid-cols-4">
                            <legend class="sr-only">{{ $rule['category']->name }}</legend>
                            <label class="col-span-2 flex min-h-11 items-center gap-2 text-sm font-medium sm:col-span-1"><input type="hidden" name="rules[{{ $catId }}][enabled]" value="0"><input type="checkbox" name="rules[{{ $catId }}][enabled]" value="1" @checked($rule['enabled']) class="size-5 rounded"> {{ $rule['category']->name }}</label>
                            <div><label class="label text-xs" for="r{{ $catId }}v">Tous les</label><input id="r{{ $catId }}v" type="number" min="1" max="120" name="rules[{{ $catId }}][interval_value]" value="{{ $rule['interval_value'] }}" class="input py-2" required></div>
                            <div><label class="label text-xs" for="r{{ $catId }}u">Unité</label><select id="r{{ $catId }}u" name="rules[{{ $catId }}][interval_unit]" class="input py-2">@foreach (\App\Models\CareReminderRule::UNITS as $u => $ul)<option value="{{ $u }}" @selected($rule['interval_unit'] === $u)>{{ $ul }}</option>@endforeach</select></div>
                            <div><label class="label text-xs" for="r{{ $catId }}n">Prévenir (jours avant)</label><input id="r{{ $catId }}n" type="number" min="0" max="60" name="rules[{{ $catId }}][notice_days]" value="{{ $rule['notice_days'] }}" class="input py-2" required></div>
                        </fieldset>
                    @endforeach
                    <p class="text-xs text-slate-500">La fréquence propose la date du prochain passage à chaque soin enregistré ; le rappel est envoyé avant l'échéance puis le jour même.</p>
                    <button class="btn-primary">Enregistrer les rappels</button>
                </form>
                @endif
            </x-section>
            @endif
            @if ($appointments->isNotEmpty())
            <x-section title="Rendez-vous de l'agenda" data-care-appointments>
                <p class="-mt-1 mb-2 text-xs text-slate-500">Soins prévus dans l'agenda. Une fois réalisés, ils rejoignent l'historique ci-dessous.</p>
                @foreach ($appointments as $e)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-3 text-sm first:border-0">
                        <a href="{{ route('calendar.show', $e) }}" class="min-w-0 hover:underline">
                            <x-badge color="amber">{{ \App\Models\CalendarEvent::TYPES[$e->type] }}</x-badge> <span class="font-medium">{{ $e->title }}</span>
                            <span class="block text-xs {{ $e->starts_at->isPast() ? 'text-red-600' : 'text-slate-500' }}">{{ $e->starts_at->translatedFormat('D d/m/Y H:i') }}{{ $e->professional ? ' · '.$e->professional->fullName() : '' }}{{ $e->starts_at->isPast() ? ' · à valider' : '' }}</span>
                        </a>
                        @if ($canEdit && $e->starts_at->isPast())<a href="{{ route('horses.care.create', [$horse, 'evenement' => $e->id]) }}" class="btn-secondary shrink-0">Valider</a>@endif
                    </div>
                @endforeach
            </x-section>
            @endif
            @if ($canHealth)
            <x-section title="Historique des soins">
                <form method="GET" class="mb-3"><x-select name="category" :options="$categories" :value="request('category')" placeholder="Toutes les catégories" onchange="this.form.submit()" aria-label="Catégorie" /></form>
                @forelse ($records as $r)
                    <a href="{{ route('horses.care.show', [$horse, $r]) }}" class="flex items-start justify-between gap-3 border-t border-slate-100 py-3 text-sm first:border-0 hover:bg-slate-50">
                        <span class="min-w-0"><x-badge color="brand">{{ $r->category->name }}</x-badge> <span class="font-medium">{{ $r->reason }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $r->professional?->fullName() }} {{ $r->care_performed ? '· '.\Illuminate\Support\Str::limit($r->care_performed, 90) : '' }}</span>
                            @if ($r->next_check_on && ! $r->next_check_done_at)<span class="block text-xs {{ $r->next_check_on->isPast() ? 'text-red-600' : 'text-amber-700' }}">Prochain contrôle : {{ $r->next_check_on->format('d/m/Y') }}</span>@endif</span>
                        <span class="shrink-0 text-right text-xs text-slate-500">{{ $r->performed_at->format('d/m/Y') }}@if ($r->cost)<br>{{ number_format((float) $r->cost, 2, ',', ' ') }} €@endif</span>
                    </a>
                @empty <p class="text-sm text-slate-500">Aucun soin enregistré.</p> @endforelse
                {{ $records?->links() }}
            </x-section>
            @endif

            <x-section title="Traitements">
                @forelse ($treatments as $t)
                    <div class="border-t border-slate-100 py-3 text-sm first:border-0" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div><span class="font-semibold">{{ $t->product }}</span> <x-badge :color="$t->status === 'active' ? 'green' : 'slate'">{{ \App\Models\Treatment::STATUSES[$t->status] }}</x-badge>
                                <p class="text-slate-600">{{ collect([$t->dosage, $t->frequency])->filter()->implode(' · ') }}</p>
                                <p class="text-xs text-slate-500">Du {{ $t->starts_on->format('d/m/Y') }} {{ $t->ends_on ? 'au '.$t->ends_on->format('d/m/Y') : '' }} {{ $t->responsible ? '· responsable : '.$t->responsible->name : '' }}</p>
                                @if ($t->instructions)<p class="mt-1 whitespace-pre-line">{{ $t->instructions }}</p>@endif
                            </div>
                            <div class="flex gap-1">
                                @if ($t->track_administrations && $t->status === 'active' && ($canEdit || $t->responsible_user_id === auth()->id()))
                                    <form method="POST" action="{{ route('horses.treatments.administer', [$horse, $t]) }}">@csrf<button class="btn-secondary text-xs"><x-icon name="check" class="size-4" /> Administré</button></form>
                                @endif
                                @if ($canEdit)<button @click="edit = ! edit" class="btn-ghost text-xs">Modifier</button>@endif
                            </div>
                        </div>
                        @if ($t->track_administrations && $t->administrations->isNotEmpty())
                            <p class="mt-1 text-xs text-slate-500">Dernières administrations : {{ $t->administrations->take(5)->map(fn ($a) => $a->administered_at->format('d/m H:i').' ('.($a->author?->name ?? '—').')')->implode(', ') }}</p>
                        @endif
                        @if ($canEdit)
                        <form x-show="edit" x-cloak method="POST" action="{{ route('horses.treatments.update', [$horse, $t]) }}" class="mt-2 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2">
                            @csrf @method('PUT')
                            @include('health._treatment-fields', ['t' => $t])
                            <x-select name="status" label="Statut" :options="\App\Models\Treatment::STATUSES" :value="$t->status" />
                            <button class="btn-primary sm:col-span-2">Enregistrer</button>
                        </form>
                        @endif
                    </div>
                @empty <p class="text-sm text-slate-500">Aucun traitement.</p> @endforelse
                @if ($canEdit)
                <details class="mt-3"><summary class="cursor-pointer text-sm link">Ajouter un traitement prescrit</summary>
                    <form method="POST" action="{{ route('horses.treatments.store', $horse) }}" class="mt-2 grid gap-2 sm:grid-cols-2">
                        @csrf
                        @include('health._treatment-fields', ['t' => new \App\Models\Treatment(['starts_on' => now()])])
                        <button class="btn-primary sm:col-span-2">Ajouter</button>
                    </form>
                </details>
                @endif
            </x-section>
        </div>

        <x-section title="Observations">
            @if ($canObserve)
            <form method="POST" action="{{ route('horses.observations.store', $horse) }}" class="mb-3 space-y-2">
                @csrf
                <x-textarea name="body" rows="2" required aria-label="Observation" placeholder="Décrire ce qui a été constaté" />
                <div class="flex gap-2"><x-select name="severity" :options="\App\Models\HealthObservation::SEVERITIES" value="info" class="flex-1" aria-label="Niveau" /><button class="btn-primary">Ajouter</button></div>
            </form>
            @endif
            @forelse ($observations as $o)
                <div class="border-t border-slate-100 py-2 text-sm {{ $o->resolved_at ? 'opacity-60' : '' }}">
                    <x-badge :color="['info' => 'slate', 'watch' => 'amber', 'alert' => 'red'][$o->severity]">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge>
                    <p class="whitespace-pre-line">{{ $o->body }}</p>
                    <p class="text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->format('d/m/Y H:i') }} {{ $o->resolved_at ? '· traité' : '' }}</p>
                    @if (! $o->resolved_at && $canEdit)<form method="POST" action="{{ route('horses.observations.resolve', [$horse, $o]) }}">@csrf<button class="text-xs link">Marquer traité</button></form>@endif
                </div>
            @empty <p class="text-sm text-slate-500">Aucune observation.</p> @endforelse
        </x-section>
    </div>
    <p class="mt-6 text-center text-xs text-slate-400">Carnet de suivi : il ne remplace pas l'avis d'un vétérinaire et ne produit aucun diagnostic ni prescription.</p>
</x-layouts.app>
