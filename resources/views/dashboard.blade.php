<x-layouts.app title="Tableau de bord">
    <div class="mb-6">
        <p class="text-[15px] text-slate-500">{{ ucfirst(now()->translatedFormat('l j F')) }}</p>
        <h1 class="text-[32px] leading-tight font-bold" data-greeting>{{ \App\Support\Greeting::for(auth()->user()->name, null, auth()->id()) }}</h1>
    </div>

    @include('dashboard._invitations')

    @if ($horses->isNotEmpty())
        {{-- Gestes du quotidien à portée de pouce. --}}
        @php $np = app(\App\Services\HorseAccess::class)->navPermissions(auth()->user()); @endphp
        <div class="no-scrollbar -mx-4 mb-5 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" data-quick-actions>
            @if (in_array('sessions.create', $np))<a href="{{ route('sessions.create', ['fait' => 1]) }}" class="pill min-h-11 shrink-0 gap-2 bg-brand-800 px-4 text-white"><x-icon name="check" class="size-4" /> Je viens de monter</a>@endif
            <a href="{{ route('health.index', ['onglet' => 'suivi']) }}" class="pill min-h-11 shrink-0 gap-2 bg-white px-4 text-slate-700 ring-1 ring-slate-200"><x-icon name="doc" class="size-4" /> Suivi du jour</a>
            @if (in_array('health.edit', $np))<a href="{{ route('health.index') }}" class="pill min-h-11 shrink-0 gap-2 bg-white px-4 text-slate-700 ring-1 ring-slate-200"><x-icon name="health" class="size-4" /> Noter un soin</a>@endif
            <a href="{{ route('health.index', ['onglet' => 'poids']) }}" class="pill min-h-11 shrink-0 gap-2 bg-white px-4 text-slate-700 ring-1 ring-slate-200"><x-icon name="scale" class="size-4" /> Pesée</a>
        </div>
        {{-- Tous les chevaux : l'état du jour de chacun, et le suivi en un toucher (un par cheval ou pour tous). --}}
        @php $pendingLog = $horses->filter(fn ($h) => in_array($h->id, $logIds) && ! $todayLogs->has($h->id)); @endphp
        @php
            $treatedIds = $treatments->pluck('horse_id')->unique()->all();
            $locations = $horses->pluck('current_location')->filter()->map(fn ($l) => trim($l))->unique()->sort()->values();
            $filters = ['all' => 'Tous', 'watch' => 'À surveiller', 'treat' => 'En traitement', 'log' => 'Suivi à faire'] + $locations->mapWithKeys(fn ($l) => ['loc:'.$l => $l])->all();
        @endphp
        <section class="card mb-5 p-5 sm:p-6" data-stable-today x-data="{ f: 'all' }">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div><h2 class="text-[17px] font-semibold text-slate-900">Vos chevaux aujourd'hui</h2><p class="text-sm text-slate-500">{{ $todayLogs->count() }} suivi{{ $todayLogs->count() > 1 ? 's' : '' }} noté{{ $todayLogs->count() > 1 ? 's' : '' }} sur {{ \App\Support\Fr::count($horses->count(), 'cheval', 'chevaux') }}</p></div>
                @if ($pendingLog->count() > 1)
                    <form method="POST" action="{{ route('logs.all-good') }}" data-all-good-all>
                        @csrf
                        @foreach ($pendingLog as $h)<input type="hidden" name="horses[]" value="{{ $h->id }}">@endforeach
                        <button class="btn-primary min-h-11 px-4"><x-icon name="check" class="size-4" /> Tout va bien pour tous ({{ $pendingLog->count() }})</button>
                    </form>
                @endif
            </div>
            @if ($horses->count() >= 4)
                {{-- Écurie : filtres rapides (état du jour, traitements, lieu). --}}
                <div class="no-scrollbar -mx-1 mb-2 flex gap-1.5 overflow-x-auto px-1" role="group" aria-label="Filtrer les chevaux" data-stable-filters>
                    @foreach ($filters as $key => $label)
                        <button type="button" @click="f = @js($key)" :class="f === @js($key) ? 'bg-brand-800 text-white' : 'bg-slate-100 text-slate-700'" class="pill min-h-10 shrink-0 px-3.5 text-sm" data-stable-filter="{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
            <div class="divide-y divide-slate-100">
                @foreach ($horses as $horse)
                    @php
                        $due = $nextDue->get($horse->id); $log = $todayLogs->get($horse->id); $alert = $alertsByHorse->get($horse->id);
                        $tags = array_values(array_filter([
                            $alert || ($due && $due->next_check_on->lt(today())) ? 'watch' : null,
                            in_array($horse->id, $treatedIds) ? 'treat' : null,
                            ! $log && in_array($horse->id, $logIds) ? 'log' : null,
                            $horse->current_location ? 'loc:'.trim($horse->current_location) : null,
                        ]));
                    @endphp
                    <div class="flex items-center gap-3 py-2.5" data-stable-horse="{{ $horse->id }}" x-show="f === 'all' || @js($tags).includes(f)">
                        <a href="{{ route('horses.show', $horse) }}" class="flex min-w-0 flex-1 items-center gap-3">
                            @if ($horse->mainPhotoUrl())<img src="{{ $horse->mainPhotoUrl() }}" alt="" loading="lazy" class="size-11 shrink-0 rounded-2xl object-cover">@else<span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 font-bold text-brand-700">{{ mb_substr($horse->shortName(), 0, 1) }}</span>@endif
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-slate-900">{{ $horse->shortName() }}</span>
                                @if ($alert)
                                    <span class="flex items-center gap-1.5 truncate text-xs text-red-600" data-horse-alert><span class="size-1.5 shrink-0 rounded-full bg-current"></span>{{ \Illuminate\Support\Str::limit($alert->body, 50) }}</span>
                                @elseif ($due)
                                    <span data-horse-due class="flex items-center gap-1.5 text-xs {{ $due->next_check_on->lt(today()) ? 'text-red-600' : (today()->diffInDays($due->next_check_on) <= 14 ? 'text-amber-700' : 'text-slate-500') }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $due->category->name }} {{ $due->next_check_on->lt(today()) ? 'en retard' : ($due->next_check_on->isToday() ? 'aujourd\'hui' : 'le '.$due->next_check_on->translatedFormat('j M')) }}</span>
                                @else
                                    <span class="flex items-center gap-1.5 text-xs text-brand-700"><span class="size-1.5 rounded-full bg-current"></span>À jour</span>
                                @endif
                            </span>
                        </a>
                        @if ($log)
                            <x-badge :color="$log->anomalies || $log->general_state === 'poor' ? 'amber' : 'green'"><x-icon name="check" class="size-3.5" /> Suivi {{ $log->logged_at->format('H:i') }}</x-badge>
                        @elseif (in_array($horse->id, $logIds))
                            <form method="POST" action="{{ route('horses.logs.store', $horse) }}" data-horse-all-good="{{ $horse->id }}">
                                @csrf
                                <input type="hidden" name="appetite" value="good"><input type="hidden" name="general_state" value="good"><input type="hidden" name="all_good" value="1">
                                <button class="btn-secondary min-h-11 shrink-0 px-3 text-sm" aria-label="Tout va bien pour {{ $horse->shortName() }}"><x-icon name="check" class="size-4" /> Tout va bien</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        @if ($toPlan->isNotEmpty())
            {{-- À prévoir ensemble : même soin à faire pour plusieurs chevaux, un seul rendez-vous. --}}
            <section class="card mb-5 p-5 sm:p-6" data-to-plan x-data="{ open: null }" @keydown.escape.window="open = null">
                <h2 class="mb-1 text-[17px] font-semibold text-slate-900">À prévoir ensemble</h2>
                <p class="mb-3 text-sm text-slate-500">Un seul passage de l'intervenant pour plusieurs chevaux.</p>
                @foreach ($toPlan as $categoryId => $group)
                    @php $first = $group->first(); $pros = $group->pluck('professional')->filter()->unique('id'); @endphp
                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 py-3 first:border-0" data-to-plan-group="{{ $categoryId }}">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-900">{{ $first->category->name }} · {{ \App\Support\Fr::count($group->count(), 'cheval', 'chevaux') }}</p>
                            <p class="text-sm text-slate-500">{{ $group->map(fn ($c) => $c->horse->shortName().' ('.($c->next_check_on->lt(today()) ? 'en retard' : $c->next_check_on->format('d/m')).')')->implode(', ') }}</p>
                            @if ($pros->isNotEmpty())<p class="text-xs text-slate-500">Habituellement : {{ $pros->map(fn ($p) => $p->fullName())->implode(', ') }}</p>@endif
                        </div>
                        <button type="button" class="btn-primary min-h-11 px-4 text-sm" @click="open = {{ $categoryId }}" data-to-plan-open="{{ $categoryId }}"><x-icon name="calendar" class="size-4" /> Rendez-vous groupé</button>
                    </div>
                    <template x-teleport="body">
                        <div x-show="open === {{ $categoryId }}" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-900/45 sm:items-center" @click.self="open = null" role="dialog" aria-modal="true" aria-label="Rendez-vous groupé {{ $first->category->name }}">
                            <div class="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-xl sm:rounded-3xl">
                                <div class="mb-3 flex items-start justify-between gap-3"><h2 class="text-lg font-semibold">{{ $first->category->name }} : rendez-vous groupé</h2><button type="button" class="btn-ghost px-2" @click="open = null" aria-label="Fermer"><x-icon name="x" /></button></div>
                                <form method="POST" action="{{ route('horses.care.check.plan', [$first->horse_id, $first]) }}" class="space-y-3" data-to-plan-form="{{ $categoryId }}">
                                    @csrf
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-field name="date" type="date" label="Date" :value="max($first->next_check_on, today())->toDateString()" :min="today()->toDateString()" :id="'grp_date_'.$categoryId" required />
                                        <x-field name="time" type="time" label="Heure" value="09:00" :id="'grp_time_'.$categoryId" />
                                    </div>
                                    <fieldset class="rounded-2xl border border-slate-200 p-3">
                                        <legend class="px-1 text-sm font-medium text-slate-700">Chevaux du rendez-vous</legend>
                                        <p class="flex min-h-11 items-center gap-3 text-sm"><x-icon name="check" class="size-5 text-brand-700" /><span><span class="font-medium">{{ $first->horse->shortName() }}</span> <span class="text-slate-500">· dernier passage le {{ $first->performed_at->format('d/m/Y') }}</span></span></p>
                                        @foreach ($group->skip(1) as $c)
                                            <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="with[]" value="{{ $c->id }}" checked class="size-5 rounded border-slate-300 text-brand-700"><span><span class="font-medium">{{ $c->horse->shortName() }}</span> <span class="text-slate-500">· dernier passage le {{ $c->performed_at->format('d/m/Y') }}</span></span></label>
                                        @endforeach
                                    </fieldset>
                                    <button class="btn-primary min-h-11 w-full justify-center"><x-icon name="calendar" class="size-4" /> Créer le rendez-vous</button>
                                </form>
                            </div>
                        </div>
                    </template>
                @endforeach
            </section>
        @endif

        <div class="mb-5 grid gap-4 lg:grid-cols-3" data-dashboard-today>
            <x-section title="Aujourd'hui" class="lg:col-span-2" :action="route('calendar.index')" action-label="Agenda">
                @forelse ($today as $e)
                    <a href="{{ route('calendar.show', $e) }}" class="-mx-2 flex items-center gap-4 rounded-2xl px-2 py-2.5 hover:bg-slate-50">
                        <span class="w-14 shrink-0 text-sm font-semibold tabular-nums {{ $e->status === 'done' ? 'text-slate-400' : 'text-slate-900' }}">{{ $e->all_day ? 'Jour' : $e->starts_at->format('H:i') }}</span>
                        <span class="h-9 w-1 shrink-0 rounded-full {{ ['veterinary' => 'bg-red-400', 'farrier' => 'bg-amber-400', 'care' => 'bg-rose-400', 'treatment' => 'bg-purple-400', 'session' => 'bg-emerald-500', 'lesson' => 'bg-teal-400', 'competition' => 'bg-blue-400', 'ride' => 'bg-lime-400', 'rest' => 'bg-slate-300', 'custom' => 'bg-sky-400'][$e->type] ?? 'bg-slate-300' }}"></span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium {{ $e->status === 'done' ? 'text-slate-500' : '' }}">{{ $e->title }}</span><span class="block truncate text-sm text-slate-500">{{ $e->horse?->shortName() }}</span></span>
                        @if ($e->status === 'done')<x-badge color="green"><x-icon name="check" class="size-3.5" /> Réalisé</x-badge>@elseif ($e->status === 'cancelled')<x-badge>Annulé</x-badge>@endif
                    </a>
                @empty
                    <p class="py-2 text-[15px] text-slate-500">Rien de prévu aujourd'hui.</p>
                @endforelse
                @if ($events->isNotEmpty())
                    <p class="mt-4 mb-1 text-xs font-medium tracking-wide text-slate-400 uppercase">Ensuite</p>
                    @foreach ($events as $e)
                        <a href="{{ route('calendar.show', $e) }}" class="-mx-2 flex items-center gap-4 rounded-2xl px-2 py-2 text-sm hover:bg-slate-50">
                            <span class="w-14 shrink-0 text-slate-500">{{ $e->starts_at->translatedFormat('D j') }}</span>
                            <span class="min-w-0 flex-1 truncate"><span class="font-medium">{{ $e->title }}</span> <span class="text-slate-500">· {{ $e->horse?->shortName() }}</span></span>
                            <span class="text-slate-400">{{ $e->all_day ? '' : $e->starts_at->format('H:i') }}</span>
                        </a>
                    @endforeach
                @endif
            </x-section>
            <x-section title="Échéances" :action="route('health.index')" action-label="Santé">
                @forelse ($careDue as $c)
                    <a href="{{ route('horses.care.index', $c->horse_id) }}#controles" class="-mx-2 flex items-center justify-between gap-3 rounded-2xl px-2 py-2.5 hover:bg-slate-50">
                        <span class="min-w-0"><span class="block truncate font-medium">{{ $c->category->name }}</span><span class="block text-sm text-slate-500">{{ $c->horse->shortName() }}</span></span>
                        <x-badge :color="$c->next_check_on->isPast() ? 'red' : (today()->diffInDays($c->next_check_on) <= 7 ? 'amber' : 'slate')">{{ $c->next_check_on->isPast() ? 'En retard' : $c->next_check_on->translatedFormat('j M') }}</x-badge>
                    </a>
                @empty <p class="py-2 text-[15px] text-slate-500">Aucune échéance dans le mois.</p> @endforelse
            </x-section>
        </div>

        <a href="{{ route('digest.show') }}" class="mb-5 flex items-center gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 hover:ring-brand-300" data-digest-link>
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-gold-100 text-gold-700"><x-icon name="calendar" /></span>
            <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-900">Ma semaine</span><span class="block text-sm text-slate-500">Rendez-vous, traitements, paiements et bilans des 7 prochains jours</span></span>
            <x-icon name="chevron" class="size-5 shrink-0 text-slate-400" />
        </a>

    @endif

        @include('dashboard._suggestions')

    @if ($horses->isEmpty())
        <x-empty title="Bienvenue ! Commencez par ajouter votre cheval" icon="horse">
            Saisissez sa fiche manuellement ou retrouvez ses informations publiques sur Internet.
            <x-slot:actions>
                <a href="{{ route('horses.create') }}" class="btn-primary">Ajouter un cheval</a>
            </x-slot:actions>
        </x-empty>
    @else
        {{-- Assistant --}}
        <button type="button" @click="$store.assistant.open(true)" class="mb-5 flex w-full items-center gap-4 rounded-3xl bg-brand-900 p-5 text-left text-white shadow-[var(--shadow-float)] transition active:scale-[.99]">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-white/10"><x-icon name="mic" class="size-7" /></span>
            <span class="min-w-0"><span class="block text-[17px] font-semibold">Dites-le simplement</span><span class="block text-sm text-white/70">« Le maréchal vient le 2 décembre à 9 h », « Séance de dressage pour la rectitude »…</span></span>
        </button>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Traitements de tous les chevaux, regroupés : « Administré » directement. --}}
            <x-section title="Traitements à donner" data-stable-treatments>
                @forelse ($treatments->groupBy('horse_id') as $horseTreatments)
                    <p class="mt-2 text-xs font-semibold tracking-wide text-slate-500 uppercase first:mt-0">{{ $horseTreatments->first()->horse->shortName() }}</p>
                    @foreach ($horseTreatments as $t)
                        @php $doneToday = $t->administrations->count(); $target = $t->times_per_day ?: 1; @endphp
                        <div class="flex items-center gap-3 border-t border-slate-100 py-2 text-sm first:border-0" data-stable-treatment="{{ $t->id }}">
                            <a href="{{ route('horses.care.index', $t->horse_id) }}" class="min-w-0 flex-1"><span class="font-medium">{{ $t->product }}</span><span class="block text-xs text-slate-500">{{ $t->dosage }} {{ $t->ends_on ? '· jusqu\'au '.$t->ends_on->format('d/m') : '' }}</span></a>
                            @if ($t->track_administrations && (in_array($t->horse_id, $healthEditIds) || ($t->responsible_user_id === auth()->id() && in_array($t->horse_id, $logIds))))
                                @if ($doneToday >= $target)
                                    <x-badge color="green"><x-icon name="check" class="size-3.5" /> Fait</x-badge>
                                @else
                                    <form method="POST" action="{{ route('horses.treatments.administer', [$t->horse_id, $t]) }}">@csrf<button class="btn-secondary min-h-11 px-3 text-sm"><x-icon name="check" class="size-4" /> Administré{{ $target > 1 ? ' ('.$doneToday.'/'.$target.')' : '' }}</button></form>
                                @endif
                            @endif
                        </div>
                    @endforeach
                @empty <p class="text-sm text-slate-500">Aucun traitement en cours.</p> @endforelse
            </x-section>
            <x-section title="Dernières séances" :action="route('sessions.index')">
                @forelse ($sessions as $s)
                    <a href="{{ route('sessions.show', $s) }}" class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                        <span><span class="font-medium">{{ $s->horse->shortName() }}</span> · {{ $s->typeLabel() }}<span class="block text-xs text-slate-500">{{ $s->scheduled_at->format('d/m H:i') }} · {{ $s->riderLabel() }}</span></span>
                        <x-time-status :status="$s->timeStatus()" />
                    </a>
                @empty <p class="text-sm text-slate-500">Aucune séance enregistrée.</p> @endforelse
            </x-section>
            @if ($observations->isNotEmpty() || $logs->isNotEmpty())
            <x-section title="Observations récentes">
                @foreach ($observations as $o)
                    <div class="border-t border-slate-100 py-2 text-sm first:border-0"><x-badge :color="$o->severity === 'alert' ? 'red' : 'amber'">{{ \App\Models\HealthObservation::SEVERITIES[$o->severity] }}</x-badge> <span class="font-medium">{{ $o->horse->shortName() }}</span><p class="text-slate-700">{{ \Illuminate\Support\Str::limit($o->body, 140) }}</p><p class="text-xs text-slate-500">{{ $o->author?->name }} · {{ $o->observed_at->diffForHumans() }}</p></div>
                @endforeach
                @foreach ($logs as $l)
                    <div class="border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $l->horse->shortName() }}</span> — suivi du {{ $l->logged_at->format('d/m H:i') }}{{ $l->anomalies ? ' · ⚠ '.\Illuminate\Support\Str::limit($l->anomalies, 80) : '' }}</div>
                @endforeach
            </x-section>
            @endif
            @if ($expenses->isNotEmpty())
            <x-section title="Dépenses récentes" :action="route('budget.index')">
                @foreach ($expenses as $e)
                    <div class="flex justify-between border-t border-slate-100 py-2 text-sm first:border-0"><span>{{ $e->category->name }} · {{ $e->horse?->shortName() }}<span class="block text-xs text-slate-500">{{ $e->spent_on->format('d/m/Y') }}</span></span><span class="font-semibold">{{ number_format((float) $e->amount, 2, ',', ' ') }} €</span></div>
                @endforeach
            </x-section>
            @endif
        </div>
    @endif
</x-layouts.app>
