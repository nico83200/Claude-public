<x-layouts.app title="Séances">
    @if ($contextHorse)
        <x-horse-header :horse="$contextHorse">
            @if ($canCreate)<a href="{{ route('sessions.create', ['horse' => $contextHorse->id]) }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Séance</a>@endif
            <a href="{{ route('horses.export.sessions', [$contextHorse->id, 'pdf']) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> PDF <x-locked-mark :org="$contextHorse->organization" feature="exports" /></a>
            <a href="{{ route('horses.export.sessions', [$contextHorse->id, 'csv']) }}" class="btn-ghost">CSV <x-locked-mark :org="$contextHorse->organization" feature="exports" /></a>
        </x-horse-header>
    @else
        <x-page-header title="Séances" subtitle="Historique et progression">
            <a href="{{ route('exercises.index') }}" class="btn-ghost" data-exercises-link><x-icon name="book" class="size-4" /> Exercices</a>
            <a href="{{ route('templates.index') }}" class="btn-ghost">Modèles</a>
            @if ($canCreate)<a href="{{ route('sessions.create') }}" class="btn-primary"><x-icon name="plus" /> Séance</a>@endif
        </x-page-header>
    @endif

    <x-filters :fields="array_values(array_filter(['rider', 'discipline', 'type', 'status', 'from', 'to', $contextHorse || $horses->count() < 2 ? null : 'horse']))" :keep="['horse' => $contextHorse?->id]">
        @unless ($contextHorse || $horses->count() < 2)<x-select name="horse" label="Cheval" :options="$horses" :value="request('horse')" placeholder="Tous" />@endunless
        <x-select name="status" label="Statut" :options="\App\Models\RidingSession::STATUSES" :value="request('status')" placeholder="Tous" />
        <x-select name="type" label="Type" :options="$typeFilter" :value="request('type')" placeholder="Tous" />
        <x-select name="discipline" label="Discipline" :options="\App\Models\Horse::DISCIPLINES" :value="request('discipline')" placeholder="Toutes" />
        <x-select name="rider" label="Cavalier" :options="$riders" :value="request('rider')" placeholder="Tous" />
        <div><label class="label" for="f_from">Du</label><input id="f_from" type="date" name="from" value="{{ request('from') }}" class="input"></div>
        <div><label class="label" for="f_to">Au</label><input id="f_to" type="date" name="to" value="{{ request('to') }}" class="input"></div>
    </x-filters>

    @if ($sessions->isEmpty())
        <x-empty title="Aucune séance" icon="session">Préparez une séance, réalisez-la avec le mode séance sur téléphone puis faites le bilan.</x-empty>
    @else
        <x-swipe.list hint-key="jc-hint-swipe-sessions" :hint="count($deletable) ? 'glissez une séance vers la gauche pour la supprimer. Vous pourrez annuler juste après.' : null">
            <div class="space-y-2">
                @foreach ($sessions as $s)
                    <x-swipe.row :delete-url="in_array($s->id, $deletable, true) ? route('sessions.destroy', $s) : null" :text="'Séance du '.$s->scheduled_at->format('d/m').' supprimée'">
                        <a href="{{ route('sessions.show', $s) }}" class="card relative flex items-center gap-4 p-4 transition hover:shadow-md" draggable="false">
                            <div class="flex size-12 shrink-0 flex-col items-center justify-center rounded-2xl bg-brand-50 text-brand-800"><span class="text-lg leading-none font-bold">{{ $s->scheduled_at->format('d') }}</span><span class="text-[10px] font-medium uppercase">{{ $s->scheduled_at->translatedFormat('M') }}</span></div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-slate-900">@unless ($contextHorse || $horses->count() < 2){{ $s->horse->shortName() }} · @endunless{{ $s->typeLabel() }}</p>
                                @if ($s->objective)<p class="line-clamp-1 text-sm text-slate-600">{{ $s->objective }}</p>@endif
                                <p class="mt-0.5 text-xs text-slate-500">{{ $s->scheduled_at->format('H:i') }} · {{ $s->riderLabel() }}{{ $s->actual_minutes ? ' · '.$s->actual_minutes.' min' : ($s->planned_minutes ? ' · '.$s->planned_minutes.' min' : '') }}</p>
                            </div>
                            <x-badge :color="['completed' => 'green', 'in_progress' => 'amber', 'cancelled' => 'red'][$s->status] ?? 'slate'">{{ \App\Models\RidingSession::STATUSES[$s->status] }}</x-badge>
                        </a>
                    </x-swipe.row>
                @endforeach
            </div>
        </x-swipe.list>
        <div class="mt-4">{{ $sessions->links() }}</div>
    @endif
    @if ($stats)
        <h2 class="mt-8 mb-3 text-lg font-semibold">Progression</h2>
        <div class="mb-4 grid gap-4 lg:grid-cols-3">
            <x-section title="90 derniers jours">
                <p class="text-3xl font-bold">{{ $stats['count_90'] }} <span class="text-base font-normal text-slate-500">séances</span></p>
                <p class="text-sm text-slate-600">{{ intdiv($stats['minutes_90'], 60) }} h {{ $stats['minutes_90'] % 60 }} min de travail</p>
                <div class="mt-3 space-y-1 text-sm">@foreach ($stats['by_type'] as $type => $c)<div class="flex justify-between"><span>{{ \App\Models\RidingSession::allTypes()[$type] ?? $type }}</span><span class="font-medium">{{ $c }}</span></div>@endforeach</div>
            </x-section>
            <x-section title="Fréquence et durée (12 semaines)">
                @php $max = max(1, $stats['by_week']->max('minutes') ?? 1); @endphp
                <div class="flex h-32 items-end gap-1" role="img" aria-label="Minutes de travail par semaine">
                    @foreach ($stats['by_week'] as $week => $w)
                        <div class="flex flex-1 flex-col items-center gap-1" title="Semaine du {{ $week }} : {{ $w['count'] }} séance(s), {{ $w['minutes'] }} min">
                            <div class="w-full rounded-t bg-brand-500" style="height: {{ max(4, round($w['minutes'] / $max * 100)) }}px"></div>
                            <span class="text-[9px] text-slate-500">{{ $week }}</span>
                        </div>
                    @endforeach
                </div>
            </x-section>
            <x-section title="Exercices les plus pratiqués">
                @forelse ($stats['top_exercises'] as $e)<div class="flex justify-between py-0.5 text-sm"><span>{{ $e->name }}</span><span class="text-slate-500">{{ $e->c }}× {{ $e->difficult ? '· '.$e->difficult.' difficile(s)' : '' }}</span></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse
            </x-section>
            @if ($stats['to_rework']->isNotEmpty())
            <x-section title="Points à retravailler" class="lg:col-span-2">
                @foreach ($stats['to_rework'] as $s)<a href="{{ route('sessions.show', $s->id) }}" class="block border-t border-slate-100 py-1.5 text-sm first:border-0"><span class="text-xs text-slate-500">{{ $s->scheduled_at->format('d/m') }}</span> {{ $s->to_rework }}</a>@endforeach
            </x-section>
            @endif
            @if ($stats['objectives']->isNotEmpty())
            <x-section title="Objectifs récurrents">
                @foreach ($stats['objectives'] as $o => $c)<div class="flex justify-between text-sm"><span>{{ $o }}</span><span class="text-slate-500">{{ $c }}×</span></div>@endforeach
            </x-section>
            @endif
        </div>
    @endif

</x-layouts.app>
