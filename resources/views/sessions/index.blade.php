<x-layouts.app title="Séances">
    <x-page-header title="Séances" subtitle="Historique, recherche et progression">
        <a href="{{ route('templates.index') }}" class="btn-ghost">Modèles</a>
        @if (request('horse'))<a href="{{ route('horses.export.sessions', [request('horse'), 'csv']) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> CSV</a><a href="{{ route('horses.export.sessions', [request('horse'), 'pdf']) }}" class="btn-ghost">PDF</a>@endif
        @if ($canCreate)<a href="{{ route('sessions.create', ['horse' => request('horse')]) }}" class="btn-primary"><x-icon name="plus" /> Nouvelle séance</a>@endif
    </x-page-header>

    <form method="GET" class="mb-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <x-select name="horse" :options="$horses" :value="request('horse')" placeholder="Tous les chevaux" aria-label="Cheval" />
        <x-select name="rider" :options="$riders" :value="request('rider')" placeholder="Tous les cavaliers" aria-label="Cavalier" />
        <x-select name="discipline" :options="\App\Models\Horse::DISCIPLINES" :value="request('discipline')" placeholder="Toutes disciplines" aria-label="Discipline" />
        <x-select name="type" :options="\App\Models\RidingSession::TYPES" :value="request('type')" placeholder="Tous types" aria-label="Type" />
        <x-select name="status" :options="\App\Models\RidingSession::STATUSES" :value="request('status')" placeholder="Tous statuts" aria-label="Statut" />
        <input type="date" name="from" value="{{ request('from') }}" class="input" aria-label="Du">
        <input type="date" name="to" value="{{ request('to') }}" class="input" aria-label="Au">
        <button class="btn-secondary">Filtrer</button>
    </form>

    @if ($stats)
        <div class="mb-4 grid gap-4 lg:grid-cols-3">
            <x-section title="90 derniers jours">
                <p class="text-3xl font-bold">{{ $stats['count_90'] }} <span class="text-base font-normal text-slate-500">séances</span></p>
                <p class="text-sm text-slate-600">{{ intdiv($stats['minutes_90'], 60) }} h {{ $stats['minutes_90'] % 60 }} min de travail</p>
                <div class="mt-3 space-y-1 text-sm">@foreach ($stats['by_type'] as $type => $c)<div class="flex justify-between"><span>{{ \App\Models\RidingSession::TYPES[$type] ?? $type }}</span><span class="font-medium">{{ $c }}</span></div>@endforeach</div>
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

    @if ($sessions->isEmpty())
        <x-empty title="Aucune séance" icon="session">Préparez une séance, réalisez-la avec le mode séance sur téléphone puis faites le bilan.</x-empty>
    @else
        <div class="space-y-2">
            @foreach ($sessions as $s)
                <a href="{{ route('sessions.show', $s) }}" class="card flex items-center gap-3 p-3 hover:border-brand-300">
                    <div class="w-14 shrink-0 text-center"><p class="text-lg leading-none font-bold">{{ $s->scheduled_at->format('d') }}</p><p class="text-xs text-slate-500 uppercase">{{ $s->scheduled_at->translatedFormat('M') }}</p></div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ $s->horse->shortName() }} · {{ $s->typeLabel() }}{{ $s->objective ? ' — '.$s->objective : '' }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $s->scheduled_at->format('H:i') }} · {{ $s->riderLabel() }} {{ $s->actual_minutes ? '· '.$s->actual_minutes.' min' : ($s->planned_minutes ? '· '.$s->planned_minutes.' min prévues' : '') }} · {{ $s->done_count }} exercice(s) réalisé(s)</p>
                    </div>
                    <x-badge :color="['completed' => 'green', 'in_progress' => 'amber', 'cancelled' => 'red'][$s->status] ?? 'slate'">{{ \App\Models\RidingSession::STATUSES[$s->status] }}</x-badge>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $sessions->links() }}</div>
    @endif
</x-layouts.app>
