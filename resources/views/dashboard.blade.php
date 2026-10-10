<x-layouts.app title="Tableau de bord">
    <x-page-header title="Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}" :subtitle="now()->translatedFormat('l j F Y')">
        @if ($horses->isNotEmpty())<a href="{{ route('sessions.create') }}" class="btn-primary"><x-icon name="plus" /> Séance</a>@endif
    </x-page-header>

    @foreach ($invitations as $inv)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm">
            <span><strong>{{ $inv->inviter->name }}</strong> vous invite {{ $inv->type === 'horse_access' ? 'à accéder au dossier de '.$inv->horse?->shortName() : 'à rejoindre '.$inv->organization?->name }}.</span>
            <span class="text-xs text-slate-500">Utilisez le lien reçu par email pour l'accepter.</span>
        </div>
    @endforeach
    @foreach ($pendingAssignments as $a)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
            <span>Demande de prise en charge de <strong>{{ $a->horse->shortName() }}</strong> par {{ $a->organization->name }}.</span>
            <a href="{{ route('organization.show') }}" class="btn-secondary">Examiner</a>
        </div>
    @endforeach

    @if ($horses->isEmpty())
        <x-empty title="Bienvenue ! Commencez par ajouter votre cheval" icon="horse">
            Saisissez sa fiche manuellement ou retrouvez ses informations publiques sur Internet.
            <x-slot:actions>
                <a href="{{ route('horses.create') }}" class="btn-primary">Ajouter un cheval</a>
                <a href="{{ route('horse-search.form') }}" class="btn-secondary">Rechercher mon cheval</a>
            </x-slot:actions>
        </x-empty>
    @else
        <div class="mb-6 flex snap-x gap-3 overflow-x-auto pb-2">
            @foreach ($horses as $horse)
                <a href="{{ route('horses.show', $horse) }}" class="card flex w-40 shrink-0 snap-start flex-col items-center p-3 text-center hover:border-brand-300">
                    @if ($horse->mainPhotoUrl())<img src="{{ $horse->mainPhotoUrl() }}" alt="" loading="lazy" class="mb-2 size-16 rounded-full object-cover">@else<div class="mb-2 flex size-16 items-center justify-center rounded-full bg-brand-100 text-2xl font-bold text-brand-800">{{ mb_substr($horse->shortName(), 0, 1) }}</div>@endif
                    <span class="w-full truncate text-sm font-semibold">{{ $horse->shortName() }}</span>
                    <span class="w-full truncate text-xs text-slate-500">{{ $horse->breed?->name ?? '—' }}</span>
                </a>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-section title="Prochains rendez-vous" :action="route('calendar.index')">
                @forelse ($events as $e)
                    <a href="{{ route('calendar.show', $e) }}" class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0 hover:bg-slate-50">
                        <span><span class="font-medium">{{ $e->title }}</span><span class="block text-xs text-slate-500">{{ $e->horse?->shortName() }}</span></span>
                        <span class="text-right text-xs text-slate-600">{{ $e->starts_at->translatedFormat('D j M') }}<br>{{ $e->all_day ? 'Journée' : $e->starts_at->format('H:i') }}</span>
                    </a>
                @empty <p class="text-sm text-slate-500">Rien de prévu dans les 14 prochains jours.</p> @endforelse
            </x-section>
            <x-section title="Rappels de soins" :action="route('health.index')">
                @forelse ($careDue as $c)
                    <a href="{{ route('horses.care.show', [$c->horse_id, $c]) }}" class="flex items-center justify-between border-t border-slate-100 py-2 text-sm first:border-0">
                        <span><span class="font-medium">{{ $c->category->name }}</span><span class="block text-xs text-slate-500">{{ $c->horse->shortName() }}</span></span>
                        <x-badge :color="$c->next_check_on->isPast() ? 'red' : 'amber'">{{ $c->next_check_on->isPast() ? 'En retard' : $c->next_check_on->format('d/m') }}</x-badge>
                    </a>
                @empty <p class="text-sm text-slate-500">Aucune échéance proche.</p> @endforelse
            </x-section>
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
