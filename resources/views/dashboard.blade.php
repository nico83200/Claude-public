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
        <section class="card mb-5 p-5 sm:p-6" data-stable-today>
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
            <div class="divide-y divide-slate-100">
                @foreach ($horses as $horse)
                    @php $due = $nextDue->get($horse->id); $log = $todayLogs->get($horse->id); $alert = $alertsByHorse->get($horse->id); @endphp
                    <div class="flex items-center gap-3 py-2.5" data-stable-horse="{{ $horse->id }}">
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
                        <x-badge :color="$s->status === 'completed' ? 'green' : 'slate'">{{ \App\Models\RidingSession::STATUSES[$s->status] }}</x-badge>
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
