<x-layouts.app title="Tableau de bord">
    <div class="mb-6">
        <p class="text-[15px] text-slate-500">{{ ucfirst(now()->translatedFormat('l j F')) }}</p>
        <h1 class="text-[32px] leading-tight font-bold" data-greeting>{{ \App\Support\Greeting::for(auth()->user()->name, null, auth()->id()) }}</h1>
    </div>

    @foreach ($invitations as $inv)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm">
            <span><strong>{{ $inv->inviter->name }}</strong> vous invite {{ $inv->type === 'horse_access' ? 'à accéder au dossier de '.$inv->horse?->shortName() : 'à rejoindre '.$inv->organization?->name }}.</span>
            <span class="flex gap-2">
                <form method="POST" action="{{ route('invitations.respond', $inv) }}">@csrf<input type="hidden" name="decision" value="decline"><button class="btn-ghost">Refuser</button></form>
                <form method="POST" action="{{ route('invitations.respond', $inv) }}">@csrf<input type="hidden" name="decision" value="accept"><button class="btn-primary">Accepter</button></form>
            </span>
        </div>
    @endforeach
    @foreach ($pendingAssignments as $a)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
            <span>Demande de prise en charge de <strong>{{ $a->horse->shortName() }}</strong> par {{ $a->organization->name }}.</span>
            <a href="{{ route('organization.show') }}" class="btn-secondary">Examiner</a>
        </div>
    @endforeach

    @if ($horses->isNotEmpty())
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

        {{-- À faire : une seule suggestion à la fois (démarrage, fiche d'urgence, notifications, offre), pour garder l'essentiel en haut. --}}
        <div class="mb-5" data-suggestions
             x-data="{ i: 0, ids: [], refresh() { this.ids = [...this.$el.querySelectorAll('[data-suggestion]')].filter((e) => e.dataset.available !== '0').map((e) => e.dataset.suggestion); if (this.i >= this.ids.length) this.i = 0; }, cur() { return this.ids[this.i]; }, next() { this.i = (this.i + 1) % this.ids.length; } }"
             x-init="$nextTick(() => refresh())" @suggestions-changed="$nextTick(() => refresh())" x-show="ids.length" x-cloak>
        @if ($onboarding)
            @php $doneCount = collect($onboarding)->where('done', true)->count(); $nextStep = collect($onboarding)->firstWhere('done', false); @endphp
            <section data-suggestion="onboarding" x-show="cur() === 'onboarding'" class="card p-5 sm:p-6" data-onboarding x-data="{ all: false }">
                <div class="mb-1 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[17px] font-semibold">Bien démarrer avec Jack&amp;Cie</h2>
                        <p class="text-sm text-slate-500">{{ $doneCount }} étape{{ $doneCount > 1 ? 's' : '' }} sur {{ count($onboarding) }} · quelques minutes suffisent</p>
                    </div>
                    <form method="POST" action="{{ route('onboarding.dismiss') }}">@csrf<button class="btn-ghost min-h-9 px-2 text-sm text-slate-500" aria-label="Masquer le démarrage">Masquer</button></form>
                </div>
                <div class="mt-3 mb-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-600 transition-all" style="width: {{ round($doneCount / count($onboarding) * 100) }}%"></div></div>
                <ol class="divide-y divide-slate-100">
                    @foreach ($onboarding as $step)
                        {{-- Seule la prochaine étape est affichée ; « Voir toutes les étapes » déplie la liste. --}}
                        <li class="flex items-center gap-3 py-3" data-onboarding-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? '1' : '0' }}" @if (($nextStep['key'] ?? null) !== $step['key']) x-show="all" x-cloak @endif>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-brand-600 text-white' : 'border-2 border-slate-300 text-slate-400' }}">@if ($step['done'])<x-icon name="check" class="size-4" />@else<span class="text-sm font-semibold">{{ $loop->iteration }}</span>@endif</span>
                            <span class="min-w-0 flex-1"><span class="block font-medium {{ $step['done'] ? 'text-slate-500' : 'text-slate-900' }}">{{ $step['title'] }}</span>@unless ($step['done'])<span class="block text-sm text-slate-500">{{ $step['text'] }}</span>@endunless</span>
                            @if (! $step['done'] && $step['url'])
                                <a href="{{ $step['url'] }}" class="btn-secondary min-h-9 shrink-0 px-4 text-sm">{{ $step['button'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <button type="button" class="mt-1 text-sm font-medium text-brand-700" @click="all = ! all" x-text="all ? 'Réduire' : 'Voir toutes les étapes'">Voir toutes les étapes</button>
            </section>
        @endif
        @if ($emergencyCta)
                <section data-suggestion="emergency" x-show="cur() === 'emergency'" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-red-100" data-emergency-cta>
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700"><x-icon name="alert" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-slate-900">Préparez la fiche d'urgence de {{ $emergencyCta->shortName() }}</h2>
                        <p class="mt-0.5 text-sm text-slate-600">Un QR code à coller sur le box : en cas de problème, n'importe qui voit qui appeler, le vétérinaire, les traitements en cours et les précautions — sans compte.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <a href="{{ route('horses.emergency.edit', $emergencyCta) }}" class="btn-primary min-h-10 px-4 text-sm">Préparer la fiche</a>
                            <form method="POST" action="{{ route('onboarding.emergency.dismiss') }}">@csrf<button class="btn-ghost min-h-10 px-3 text-sm text-slate-500" data-emergency-cta-dismiss>Ne plus afficher</button></form>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if ($horses->isNotEmpty())
        {{-- Invitation à activer les notifications (application installée, pas encore autorisées) --}}
        <div x-data="{ show: false }" x-init="(() => { try { show = ! $store.install.handheld && 'Notification' in window && 'PushManager' in window && Notification.permission === 'default' && ! localStorage.getItem('jc-push-hint') } catch { } })()"
             data-suggestion="push" :data-available="show ? 1 : 0" x-effect="show; $dispatch('suggestions-changed')" x-show="show && cur() === 'push'" x-cloak class="flex items-center gap-3 rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-800"><x-icon name="bell" /></span>
            <p class="min-w-0 flex-1 text-sm text-slate-700"><span class="block font-semibold text-slate-900">Ne ratez plus un bilan</span>Recevez un rappel sur cet appareil après vos séances et vos cours.</p>
            <a href="{{ route('settings.notifications') }}" class="btn-primary min-h-10 shrink-0 px-4">Activer</a>
            <button type="button" class="shrink-0 p-1 text-slate-400" aria-label="Plus tard" @click="show = false; try { localStorage.setItem('jc-push-hint', '1') } catch { }"><x-icon name="x" class="size-4" /></button>
        </div>

        {{-- Offre gratuite : proposition d'offre supérieure (masquable 7 jours) --}}
        @if ($upsell)
            <div x-data="{ show: true }" x-init="(() => { try { show = (+localStorage.getItem('jc-upsell-later') || 0) < Date.now() } catch { } })()" data-suggestion="upsell" :data-available="show ? 1 : 0" x-effect="show; $dispatch('suggestions-changed')" x-show="show && cur() === 'upsell'" class="relative">
                <x-upsell :offer="$upsell" />
                <button type="button" class="absolute top-3 right-3 p-1.5 text-slate-400 hover:text-slate-700" aria-label="Plus tard"
                        @click="show = false; try { localStorage.setItem('jc-upsell-later', Date.now() + 7 * 864e5) } catch { }"><x-icon name="x" class="size-4" /></button>
            </div>
        @endif

        @endif
            <div x-show="ids.length > 1" class="mt-2 flex items-center justify-between px-1 text-sm text-slate-500">
                <span x-text="'Suggestion ' + (i + 1) + ' sur ' + ids.length"></span>
                <button type="button" class="inline-flex min-h-9 items-center gap-1 font-medium text-brand-700" @click="next()">Suivante <x-icon name="chevron" class="size-4" /></button>
            </div>
        </div>

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

        <div class="mt-6 mb-3 flex items-center justify-between"><h2 class="text-[17px] font-semibold">Vos chevaux</h2><a href="{{ route('horses.index') }}" class="text-sm link">Tout voir</a></div>
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($horses as $horse)
                @php $due = $nextDue->get($horse->id); @endphp
                <a href="{{ route('horses.show', $horse) }}" class="card flex flex-col p-4 transition hover:-translate-y-0.5">
                    @if ($horse->mainPhotoUrl())<img src="{{ $horse->mainPhotoUrl() }}" alt="" loading="lazy" class="mb-3 aspect-[4/3] w-full rounded-2xl object-cover">@else<div class="mb-3 flex aspect-[4/3] w-full items-center justify-center rounded-2xl bg-brand-50 text-3xl font-bold text-brand-700">{{ mb_substr($horse->shortName(), 0, 1) }}</div>@endif
                    <span class="truncate font-semibold">{{ $horse->shortName() }}</span>
                    @if ($due)
                        <span data-horse-due class="mt-1 flex items-center gap-1.5 text-xs {{ $due->next_check_on->lt(today()) ? 'text-red-600' : (today()->diffInDays($due->next_check_on) <= 14 ? 'text-amber-700' : 'text-slate-500') }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $due->category->name }} {{ $due->next_check_on->lt(today()) ? 'en retard' : ($due->next_check_on->isToday() ? 'aujourd\'hui' : 'le '.$due->next_check_on->translatedFormat('j M')) }}</span>
                    @else
                        <span class="mt-1 flex items-center gap-1.5 text-xs text-brand-700"><span class="size-1.5 rounded-full bg-current"></span>À jour</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-section title="Traitements en cours">
                @forelse ($treatments as $t)
                    <a href="{{ route('horses.care.index', $t->horse_id) }}" class="block border-t border-slate-100 py-2 text-sm first:border-0"><span class="font-medium">{{ $t->product }}</span> — {{ $t->horse->shortName() }}<span class="block text-xs text-slate-500">{{ $t->dosage }} {{ $t->ends_on ? '· jusqu\'au '.$t->ends_on->format('d/m') : '' }}</span></a>
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
