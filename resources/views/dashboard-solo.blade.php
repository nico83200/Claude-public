<x-layouts.app title="Tableau de bord">
    @php
        $can = fn (string $p) => in_array($p, $perms, true);
        $late = $checks->filter(fn ($n) => $n['care']->next_check_on->lt(today()));
        $soon = $checks->filter(fn ($n) => $n['care']->next_check_on->lte(today()->addDays(7)));
        $alert = $observations->first();
        $meta = collect([$horse->age() !== null ? $horse->age().' ans' : null, \App\Models\Horse::DISCIPLINES[$horse->main_discipline] ?? null, $horse->breed?->name])->filter();
        [$stateTone, $stateText] = match (true) {
            (bool) $alert => [$alert->severity === 'alert' ? 'red' : 'amber', 'Point d\'attention : '.\Illuminate\Support\Str::limit($alert->label(), 60)],
            $late->isNotEmpty() => ['amber', $late->first()['care']->category->name.' en retard depuis le '.$late->first()['care']->next_check_on->format('d/m')],
            $todayLog && ($todayLog->anomalies || $todayLog->general_state === 'poor') => ['amber', 'Point d\'attention noté à '.$todayLog->logged_at->format('H:i')],
            (bool) $todayLog => ['green', 'Suivi du jour noté à '.$todayLog->logged_at->format('H:i')],
            default => ['slate', 'Pas encore de suivi aujourd\'hui'],
        };
        $tones = ['red' => 'bg-red-50 text-red-800 ring-red-200', 'amber' => 'bg-amber-50 text-amber-900 ring-amber-200', 'green' => 'bg-brand-50 text-brand-900 ring-brand-200', 'slate' => 'bg-slate-100 text-slate-700 ring-slate-200'];
        $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €';
        $budgetFeature = app(\App\Services\Entitlements::class)->hasFeature($horse->organization, 'budget');
        $trend = $weights->count() === 2 ? round((float) $weights[0]->weight_kg - (float) $weights[1]->weight_kg) : null;
    @endphp

    @include('dashboard._invitations')

    @if ($otherHorses->isNotEmpty())
        {{-- Passage rapide d'un cheval à l'autre (et « Tous ») --}}
        <form method="POST" action="{{ route('focus.set') }}" class="no-scrollbar -mx-4 mb-3 flex gap-2 overflow-x-auto px-4 py-1 sm:mx-0 sm:px-1" data-horse-chips>
            @csrf
            <button name="horse" value="all" class="pill min-h-11 shrink-0 gap-2 bg-white px-3 text-sm text-slate-700 ring-1 ring-slate-200"><x-icon name="horse" class="size-4" /> Tous</button>
            @foreach ($otherHorses as $h)
                <button name="horse" value="{{ $h->id }}" @if ($h->is($horse)) aria-current="true" @endif class="pill min-h-11 shrink-0 gap-2 pl-1.5 text-sm {{ $h->is($horse) ? 'bg-brand-50 font-semibold text-brand-900 ring-2 ring-brand-500' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}" data-horse-chip="{{ $h->id }}">
                    @if ($h->mainPhotoUrl())<img src="{{ $h->mainPhotoUrl() }}" alt="" class="size-8 rounded-full object-cover">@else<span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ mb_strtoupper(mb_substr($h->shortName(), 0, 1)) }}</span>@endif
                    {{ $h->shortName() }}
                </button>
            @endforeach
        </form>
    @endif

    {{-- 1. Le cheval et son état du jour --}}
    <section class="mb-5 flex items-center gap-4 sm:gap-5" data-solo-hero>
        <a href="{{ route('horses.show', $horse) }}" class="shrink-0" aria-label="Fiche de {{ $horse->shortName() }}">
            @if ($horse->mainPhotoUrl())
                <img src="{{ $horse->mainPhotoUrl() }}" alt="" class="size-20 rounded-3xl object-cover shadow-sm sm:size-28">
            @else
                <span class="flex size-20 items-center justify-center rounded-3xl bg-brand-100 text-3xl font-bold text-brand-800 sm:size-28 sm:text-4xl">{{ mb_strtoupper(mb_substr($horse->shortName(), 0, 1)) }}</span>
            @endif
        </a>
        <div class="min-w-0 flex-1">
            <p class="text-sm text-slate-500" data-greeting>{{ ucfirst(now()->translatedFormat('l j F')) }} · {{ \App\Support\Greeting::for(auth()->user()->name, null, auth()->id()) }}</p>
            <h1 class="text-[30px] leading-tight font-bold break-words text-slate-900 sm:text-4xl">{{ $horse->shortName() }}
                @if (app(\App\Services\HorseAccess::class)->hasSeveralHorses(auth()->user()))
                    <button type="button" x-data @click="$dispatch('open-focus')" class="ml-1 inline-flex min-h-11 items-center gap-1 rounded-full px-2 align-middle text-sm font-medium text-brand-700 hover:bg-brand-50" data-solo-switch>Changer <x-icon name="chevron" class="size-4 rotate-90" /></button>
                @endif
            </h1>
            @if ($meta->isNotEmpty())<p class="text-sm text-slate-500">{{ $meta->implode(' · ') }}</p>@endif
            @if ($alert)
                {{-- Point d'attention ouvert : un toucher ouvre son suivi (historique, nouvelles, vétérinaire). --}}
                <a href="{{ route('horses.observations.show', [$horse, $alert]) }}" class="mt-2 inline-flex min-h-11 max-w-full items-center gap-1 rounded-full px-3 py-1 text-sm font-medium ring-1 {{ $tones[$stateTone] }}" data-solo-state="{{ $stateTone }}" data-solo-attention><span class="truncate">{{ $stateText }}</span><x-icon name="chevron" class="size-4 shrink-0" /></a>
            @else
                <p class="mt-2 inline-flex max-w-full items-center rounded-full px-3 py-1 text-sm font-medium ring-1 {{ $tones[$stateTone] }}" data-solo-state="{{ $stateTone }}"><span class="truncate">{{ $stateText }}</span></p>
            @endif
        </div>
    </section>

    {{-- 2. Gestes du jour : un toucher --}}
    <div class="mb-5 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap" data-solo-actions>
        @if ($can('dailylog.create'))
            @if ($todayLog)
                <a href="{{ route('horses.logs.index', $horse) }}" class="pill col-span-2 min-h-12 justify-center gap-2 bg-brand-50 px-4 font-semibold text-brand-900 ring-1 ring-brand-200 sm:col-span-1" data-solo-log-done><x-icon name="check" class="size-5" /> Suivi du jour noté</a>
            @endif
        @endif
        @if ($can('comments.create'))<a href="{{ route('horses.observations.create', $horse) }}" class="pill col-span-2 min-h-12 justify-center gap-2 bg-white px-4 font-semibold text-slate-800 ring-1 ring-amber-300 sm:col-span-1" data-solo-attention-new><x-icon name="alert" class="size-4 text-amber-600" /> Quelque chose d'inhabituel ?</a>@endif
        @if ($can('sessions.create'))<a href="{{ route('sessions.create', ['fait' => 1]) }}" class="pill min-h-12 justify-center gap-2 bg-white px-4 text-slate-800 ring-1 ring-slate-200"><x-icon name="session" class="size-4" /> Je viens de monter</a>@endif
        @if ($can('health.edit'))<a href="{{ route('horses.care.create', $horse) }}" class="pill min-h-12 justify-center gap-2 bg-white px-4 text-slate-800 ring-1 ring-slate-200"><x-icon name="health" class="size-4" /> Noter un soin</a>@endif
        <a href="{{ route('horses.weight', $horse) }}" class="pill min-h-12 justify-center gap-2 bg-white px-4 text-slate-800 ring-1 ring-slate-200"><x-icon name="scale" class="size-4" /> Pesée</a>
        @if ($can('expenses.create') && $budgetFeature)<a href="{{ route('budget.index', ['nouvelle' => 'depense']) }}" class="pill min-h-12 justify-center gap-2 bg-white px-4 text-slate-800 ring-1 ring-slate-200"><x-icon name="wallet" class="size-4" /> Dépense</a>@endif
        @if ($can('dailylog.create'))<a href="{{ route('horses.logs.index', $horse) }}" class="pill min-h-12 justify-center gap-2 bg-white px-4 text-slate-800 ring-1 ring-slate-200"><x-icon name="doc" class="size-4" /> Suivi détaillé</a>@endif
    </div>

    @include('dashboard._to-validate')

    <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
        <div class="space-y-4">
            {{-- 3. Aujourd'hui et 7 prochains jours --}}
            <x-section title="Aujourd'hui et 7 jours" :action="$can('calendar.view') ? route('calendar.index') : null" action-label="Agenda" data-solo-week>
                @php $hasWeek = $week->isNotEmpty() || $treatments->isNotEmpty() || $soon->isNotEmpty() || $competitionAlerts->isNotEmpty(); @endphp
                @include('dashboard._competition-vaccines', ['alerts' => $competitionAlerts])
                @if ($ridingWeek)
                    {{-- Jours de monte : qui monte quel jour cette semaine (cheval partagé). --}}
                    @php
                        $todayRider = $ridingWeek[today()->toDateString()][$horse->id]['user'] ?? null;
                        $todayRest = ! empty($ridingWeek[today()->toDateString()][$horse->id]['rest']);
                        $firstName = fn ($u) => \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before($u->name.' ', ' '), 7, '');
                    @endphp
                    <a href="{{ route('horses.riding-days', $horse) }}" class="mb-3 block" data-solo-riding>
                        <span class="block text-sm text-slate-700">Aujourd'hui : <strong>{{ $todayRest ? 'jour de repos' : ($todayRider ? ($todayRider->id === auth()->id() ? 'votre jour de monte' : 'jour de '.$todayRider->name) : 'jour libre') }}</strong></span>
                        <span class="no-scrollbar mt-1.5 flex gap-1 overflow-x-auto">
                            @for ($i = 0; $i < 7; $i++)
                                @php $d = today()->addDays($i); $u = $ridingWeek[$d->toDateString()][$horse->id]['user'] ?? null; $mine = $u?->id === auth()->id(); $resting = ! empty($ridingWeek[$d->toDateString()][$horse->id]['rest']); @endphp
                                <span class="flex min-w-11 flex-1 flex-col items-center rounded-xl py-1 text-[11px] leading-tight {{ $mine ? 'bg-brand-100 text-brand-900' : ($u ? 'bg-slate-100 text-slate-700' : 'bg-slate-50 text-slate-400') }}" data-solo-riding-day="{{ $d->toDateString() }}">
                                    <span class="uppercase">{{ $i === 0 ? 'Auj.' : $d->translatedFormat('D') }}</span>
                                    <span class="font-semibold">{{ $resting ? 'Repos' : ($u ? ($mine ? 'Moi' : $firstName($u)) : '—') }}</span>
                                </span>
                            @endfor
                        </span>
                    </a>
                @endif
                @foreach ($treatments as $t)
                    @php $doneToday = $t->administrations->count(); $target = $t->times_per_day ?: 1; @endphp
                    <div class="flex items-center gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0" data-solo-treatment="{{ $t->id }}">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-700"><x-icon name="health" class="size-4" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-medium text-slate-900">{{ $t->product }}</span><span class="block text-xs text-slate-500">{{ collect([$t->dosage, $t->frequency, $t->ends_on ? 'jusqu\'au '.$t->ends_on->format('d/m') : null])->filter()->implode(' · ') ?: 'Traitement en cours' }}</span></span>
                        @if ($t->track_administrations && $canAdminister($t))
                            @if ($doneToday >= $target)
                                <x-badge color="green"><x-icon name="check" class="size-3.5" /> Fait</x-badge>
                            @else
                                <form method="POST" action="{{ route('horses.treatments.administer', [$horse, $t]) }}">@csrf<button class="btn-secondary min-h-11 px-3 text-sm" data-solo-administer><x-icon name="check" class="size-4" /> Administré{{ $target > 1 ? ' ('.$doneToday.'/'.$target.')' : '' }}</button></form>
                            @endif
                        @endif
                    </div>
                @endforeach
                @foreach ($soon as $n)
                    @php $c = $n['care']; $isLate = $c->next_check_on->lt(today()); @endphp
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-slate-100 py-2.5 text-sm first:border-0" data-solo-due="{{ $c->id }}">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $isLate ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}"><x-icon name="alert" class="size-4" /></span>
                        <span class="min-w-0 flex-1"><span class="block font-medium text-slate-900">{{ $c->category->name }}</span><span class="block text-xs {{ $isLate ? 'text-red-600' : 'text-slate-500' }}">{{ $isLate ? 'En retard · prévu le ' : 'Prévu le ' }}{{ $c->next_check_on->translatedFormat('j F') }}</span></span>
                        @if ($can('health.edit'))
                            <a href="{{ route('horses.care.create', [$horse, 'suite' => $c->id]) }}" class="btn-secondary ml-auto min-h-11 px-3 text-sm" data-note-passage><x-icon name="check" class="size-4" /> Noter le passage</a>
                        @endif
                    </div>
                @endforeach
                @foreach ($week as $e)
                    <a href="{{ route('calendar.show', $e) }}" class="flex items-center gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0" data-solo-event>
                        <span class="flex w-12 shrink-0 flex-col items-center rounded-xl bg-slate-50 py-1 leading-tight"><span class="text-[11px] text-slate-500 uppercase">{{ $e->starts_at->isToday() ? 'Auj.' : $e->starts_at->translatedFormat('D') }}</span><span class="font-semibold text-slate-900">{{ $e->starts_at->format('d') }}</span></span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium text-slate-900">{{ $e->title }}</span><span class="block text-xs text-slate-500">{{ $e->all_day ? 'Toute la journée' : $e->starts_at->format('H:i') }}{{ $e->status === 'done' ? ' · réalisé' : '' }}</span></span>
                        <x-icon name="chevron" class="size-4 shrink-0 text-slate-300" />
                    </a>
                @endforeach
                @unless ($hasWeek)<p class="text-sm text-slate-500">Rien de prévu cette semaine.</p>@endunless
                <a href="{{ route('digest.show') }}" class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm link">Ma semaine en détail <x-icon name="chevron" class="size-4" /></a>
            </x-section>

            {{-- 5. Activité et régularité --}}
            @if ($sessionsWeek !== null || $can('dailylog.create'))
            <x-section title="Activité" :action="$sessionsWeek !== null ? route('sessions.index') : null" action-label="Séances" data-solo-activity>
                <div class="grid grid-cols-2 gap-3">
                    @if ($sessionsWeek !== null)
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Séances cette semaine</p><p class="mt-1 text-2xl font-bold text-slate-900" data-solo-sessions-week>{{ $sessionsWeek }}</p></div>
                    @endif
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Suivi sur 7 jours</p><p class="mt-1 text-2xl font-bold text-slate-900" data-solo-log-days>{{ $logDays }}<span class="text-sm font-medium text-slate-500"> / 7</span></p>
                        <div class="mt-1.5 flex gap-1" aria-hidden="true">@for ($d = 0; $d < 7; $d++)<span class="h-1.5 flex-1 rounded-full {{ $d < $logDays ? 'bg-brand-600' : 'bg-slate-200' }}"></span>@endfor</div></div>
                </div>
                @if ($lastSession)
                    <a href="{{ route('sessions.show', $lastSession) }}" class="mt-3 flex items-center justify-between gap-2 rounded-2xl border border-slate-100 p-3 text-sm hover:border-brand-200">
                        <span class="min-w-0"><span class="block text-xs text-slate-500">Dernière séance · {{ $lastSession->scheduled_at->diffForHumans() }}</span><span class="block truncate font-medium text-slate-900">{{ $lastSession->typeLabel() }}{{ $lastSession->objective ? ' · '.$lastSession->objective : '' }}</span>
                            @if ($lastSession->progress || $lastSession->rider_feeling)<span class="block truncate text-xs text-slate-500">{{ $lastSession->progress ? \Illuminate\Support\Str::limit($lastSession->progress, 70) : 'Ressenti : '.$lastSession->rider_feeling.'/5' }}</span>@endif</span>
                        <x-icon name="chevron" class="size-4 shrink-0 text-slate-300" />
                    </a>
                @elseif ($sessionsWeek !== null)
                    <p class="mt-3 text-sm text-slate-500">Aucune séance enregistrée pour l'instant.</p>
                @endif
            </x-section>
            @endif
        </div>

        <div class="space-y-4">
            {{-- 4. Santé en un coup d'œil --}}
            @if ($can('health.view') || $can('feeding.view'))
            <x-section title="Santé" :action="route('health.index')" action-label="Dossier santé" data-solo-health>
                @if ($can('health.view'))
                    @if ($checks->isNotEmpty())
                        {{-- Un toucher sur une pastille : prendre le rendez-vous, avec la date du dernier passage pour mémoire. --}}
                        <div class="flex flex-wrap gap-2" x-data="{ open: null }" @keydown.escape.window="open = null">
                            @foreach ($checks as $n)
                                @php $c = $n['care']; $ev = $n['event']; $tone = $c->next_check_on->lt(today()) ? 'bg-red-50 text-red-800 ring-red-200' : ($c->next_check_on->lte(today()->addDays(30)) ? 'bg-amber-50 text-amber-900 ring-amber-200' : 'bg-brand-50 text-brand-900 ring-brand-200'); @endphp
                                <button type="button" @click="open = {{ $c->id }}" class="min-h-11 rounded-2xl px-3 py-2 text-left text-sm ring-1 {{ $tone }}" data-solo-check="{{ $c->id }}">
                                    <span class="block font-medium">{{ $c->category->name }}</span>
                                    <span class="block text-xs opacity-80">{{ $ev ? 'RDV le '.$ev->starts_at->translatedFormat('j M') : ($c->next_check_on->lt(today()) ? 'en retard' : $c->next_check_on->translatedFormat('j M Y')) }}</span>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="open === {{ $c->id }}" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/45 sm:items-center" @click.self="open = null" role="dialog" aria-modal="true" aria-label="Rendez-vous {{ $c->category->name }}" data-solo-check-sheet="{{ $c->id }}">
                                        <div class="w-full max-w-md rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl">
                                            <div class="mb-3 flex items-start justify-between gap-3">
                                                <div><h2 class="text-lg font-semibold">{{ $c->category->name }}</h2><p class="text-sm text-slate-500">{{ $horse->shortName() }} · {{ $c->next_check_on->lt(today()) ? 'en retard, prévu le ' : 'prévu le ' }}{{ $c->next_check_on->translatedFormat('j F Y') }}</p></div>
                                                <button type="button" class="btn-ghost px-2" @click="open = null" aria-label="Fermer"><x-icon name="x" /></button>
                                            </div>
                                            <p class="mb-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700" data-solo-check-memo>Pour mémoire : dernier passage le <strong>{{ $c->performed_at->translatedFormat('j F Y') }}</strong>{{ $c->professional ? ' avec '.$c->professional->fullName() : '' }}.</p>
                                            @if ($ev)
                                                <p class="mb-3 text-sm text-slate-700">Rendez-vous déjà dans l'agenda le <strong>{{ $ev->starts_at->translatedFormat('j F Y') }}</strong>{{ $ev->all_day ? '' : ' à '.$ev->starts_at->format('H:i') }}.</p>
                                                <a href="{{ route('calendar.show', $ev) }}" class="btn-primary min-h-11 w-full justify-center"><x-icon name="calendar" class="size-4" /> Voir le rendez-vous</a>
                                            @elseif ($can('health.edit'))
                                                <form method="POST" action="{{ route('horses.care.check.plan', [$horse, $c]) }}" class="space-y-3" data-solo-check-plan>
                                                    @csrf
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <x-field name="date" type="date" label="Date du rendez-vous" :value="max($c->next_check_on, today())->toDateString()" :min="today()->toDateString()" :id="'rdv_date_'.$c->id" required />
                                                        <x-field name="time" type="time" label="Heure" value="09:00" :id="'rdv_time_'.$c->id" />
                                                    </div>
                                                    @if (($groupable[$c->care_category_id] ?? collect())->isNotEmpty())
                                                        <fieldset class="rounded-2xl border border-slate-200 p-3" data-solo-group="{{ $c->id }}">
                                                            <legend class="px-1 text-sm font-medium text-slate-700">Même rendez-vous pour d'autres chevaux ?</legend>
                                                            @foreach ($groupable[$c->care_category_id] as $g)
                                                                <label class="flex min-h-11 items-center gap-3 text-sm">
                                                                    <input type="checkbox" name="with[]" value="{{ $g->id }}" class="size-5 rounded border-slate-300 text-brand-700" @checked($g->next_check_on->lte($c->next_check_on->copy()->addDays(21)))>
                                                                    <span class="min-w-0 flex-1"><span class="font-medium text-slate-900">{{ $g->horse->shortName() }}</span> <span class="text-slate-500">· {{ $g->next_check_on->lt(today()) ? 'en retard' : 'prévu le '.$g->next_check_on->format('d/m') }}</span></span>
                                                                </label>
                                                            @endforeach
                                                        </fieldset>
                                                    @endif
                                                    <button class="btn-primary min-h-11 w-full justify-center"><x-icon name="calendar" class="size-4" /> Créer le rendez-vous</button>
                                                </form>
                                            @endif
                                            <div class="mt-3 grid grid-cols-2 gap-2">
                                                @if ($can('health.edit'))<a href="{{ route('horses.care.create', [$horse, 'suite' => $c->id]) }}" class="btn-secondary min-h-11 justify-center px-3 text-sm"><x-icon name="check" class="size-4" /> Noter le passage</a>@endif
                                                <a href="{{ route('horses.care.show', [$horse, $c]) }}" class="btn-ghost min-h-11 justify-center px-3 text-sm {{ $can('health.edit') ? '' : 'col-span-2' }}">Voir le dernier soin</a>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Aucune échéance enregistrée. @if ($can('health.edit'))<a href="{{ route('horses.care.create', $horse) }}" class="link">Noter un premier soin</a>@endif</p>
                    @endif
                @endif
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <a href="{{ route('horses.weight', $horse) }}" class="rounded-2xl bg-slate-50 p-3 hover:bg-brand-50" data-solo-weight>
                        <p class="text-xs text-slate-500">Poids</p>
                        @if ($weights->isNotEmpty())
                            <p class="mt-1 font-semibold text-slate-900">{{ number_format((float) $weights[0]->weight_kg, 0, ',', ' ') }} kg @if ($trend)<span class="text-xs font-medium {{ abs($trend) >= 15 ? 'text-amber-700' : 'text-slate-500' }}">{{ $trend > 0 ? '+' : '−' }}{{ abs($trend) }} kg</span>@endif</p>
                            <p class="text-xs text-slate-500">le {{ $weights[0]->measured_on->format('d/m') }}</p>
                        @else<p class="mt-1 text-sm font-medium text-brand-700">Ajouter une pesée</p>@endif
                    </a>
                    @if ($can('feeding.view'))
                    <a href="{{ route('health.index', ['onglet' => 'alimentation']) }}" class="rounded-2xl bg-slate-50 p-3 hover:bg-brand-50" data-solo-ration>
                        <p class="text-xs text-slate-500">Ration en cours</p>
                        @forelse ($rations->take(1) as $r)
                            <p class="mt-1 truncate font-semibold text-slate-900">{{ $r->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $r->entries->pluck('feed')->filter()->unique()->take(3)->implode(' · ') }}</p>
                        @empty<p class="mt-1 text-sm font-medium text-brand-700">Ajouter une ration</p>@endforelse
                    </a>
                    @endif
                </div>
            </x-section>
            @endif

            {{-- 6. Budget du mois --}}
            @if ($budget)
                <x-section title="Budget de {{ now()->translatedFormat('F') }}" :action="route('budget.index')" action-label="Budget" data-solo-budget>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Dépensé ce mois-ci</p><p class="mt-1 text-xl font-bold text-slate-900">{{ $eur($budget['month']) }}</p></div>
                        <div class="rounded-2xl p-3 {{ $budget['unpaid'] > 0 ? 'bg-amber-50' : 'bg-slate-50' }}"><p class="text-xs text-slate-500">À payer</p><p class="mt-1 text-xl font-bold {{ $budget['unpaid'] > 0 ? 'text-amber-900' : 'text-slate-900' }}">{{ $eur($budget['unpaid']) }}</p></div>
                    </div>
                    @if ($budget['next'])<p class="mt-3 text-sm text-slate-600">Prochaine charge fixe : <strong>{{ $budget['next']->label }}</strong>, {{ $eur($budget['next']->amount) }} le {{ $budget['next']->next_due_on->translatedFormat('j F') }}</p>@endif
                </x-section>
            @elseif ($budgetLocked)
                <a href="{{ route('budget.index') }}" class="card flex items-center gap-3 p-4 hover:ring-brand-300" data-solo-budget-locked>
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-gold-50 text-gold-600"><x-icon name="sparkles" /></span>
                    <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-900">Budget de {{ $horse->shortName() }}</span><span class="block text-sm text-slate-500">Suivez ses dépenses et ce qui reste à payer avec l'offre Propriétaire.</span></span>
                    <x-icon name="chevron" class="size-5 shrink-0 text-slate-400" />
                </a>
            @endif

            {{-- 7. Partage --}}
            @if ($shares !== null)
                <x-section title="Partage" :action="route('horses.sharing', $horse)" action-label="Gérer" data-solo-shares>
                    @if ($shares->isNotEmpty())
                        <div class="flex items-center gap-3">
                            <div class="flex -space-x-2">@foreach ($shares->take(5) as $g)<span class="flex size-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800 ring-2 ring-white" title="{{ $g->user?->name }}">{{ mb_strtoupper(mb_substr($g->user?->name ?? '?', 0, 1)) }}</span>@endforeach</div>
                            <p class="min-w-0 flex-1 text-sm text-slate-600">{{ $shares->take(2)->map(fn ($g) => $g->user?->name)->filter()->implode(', ') }}{{ $shares->count() > 2 ? ' et '.($shares->count() - 2).' autre'.($shares->count() > 3 ? 's' : '') : '' }}</p>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Demi-pensionnaire, écurie, proche : partagez le dossier de {{ $horse->shortName() }} en choisissant ce que chacun voit.</p>
                    @endif
                    <a href="{{ route('horses.sharing', $horse) }}" class="btn-secondary mt-3 min-h-11 w-full justify-center sm:w-auto"><x-icon name="share" class="size-4" /> Inviter quelqu'un</a>
                </x-section>
            @endif
        </div>
    </div>

    {{-- 8. Suggestions (démarrage, fiche d'urgence, notifications, offre) : une seule à la fois, en bas. --}}
    <div class="mt-5">
        @include('dashboard._suggestions', ['horses' => collect([$horse])])
    </div>
</x-layouts.app>
