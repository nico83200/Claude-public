@php
    $pct = fn ($a, $b) => \App\Http\Controllers\Admin\StatsController::pct((int) $a, (int) $b);
    $maxSignup = max(1, collect($buckets)->max('value'));
    $peak = collect($buckets)->sortByDesc('value')->keys()->first();
    $hbar = function (array|\Illuminate\Support\Collection $rows) {
        $max = max(1, collect($rows)->max());
        return collect($rows)->map(fn ($v) => ['value' => $v, 'width' => max(2, round($v / $max * 100))]);
    };
@endphp
<x-layouts.app title="Statistiques · Admin">
    <x-page-header title="Statistiques d'usage" :subtitle="'Depuis le '.$since->translatedFormat('j F Y')" />
    @include('admin._nav')

    <nav class="mb-5 flex gap-2" aria-label="Période">
        @foreach ($periods as $value => $label)
            <a href="{{ route('admin.stats.index', ['jours' => $value]) }}" @if ($value === $days) aria-current="page" @endif class="pill {{ $value === $days ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Chiffres clés --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([['Comptes', $kpis['users'], null], ['Nouveaux', $kpis['new'], 'sur la période'], ['Actifs 7 j', $kpis['active7'], $pct($kpis['active7'], $kpis['users']).' des comptes'], ['Actifs 30 j', $kpis['active30'], $pct($kpis['active30'], $kpis['users']).' des comptes'], ['Abonnés payants', $kpis['paying'], null], ['En essai', $kpis['trialing'], null]] as [$label, $value, $hint])
            <div class="card p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($value, 0, ',', ' ') }}</p>@if ($hint)<p class="text-xs text-slate-500">{{ $hint }}</p>@endif</div>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Inscriptions (une seule série : le titre la nomme, pas de légende) --}}
        <x-section :title="'Inscriptions par '.($byMonth ? 'mois' : 'semaine')" class="lg:col-span-2">
            <div class="flex h-48 items-end gap-[2px] border-b border-slate-200 pt-6" role="img" aria-label="Inscriptions par {{ $byMonth ? 'mois' : 'semaine' }}">
                @foreach ($buckets as $i => $b)
                    <div class="group relative flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                        <span class="pointer-events-none absolute bottom-full mb-1 hidden wc-tip rounded-lg px-2 py-1 text-xs whitespace-nowrap group-hover:block" style="bottom: calc({{ $b['value'] / $maxSignup * 100 }}% + 4px)">{{ $b['full'] }} : {{ $b['value'] }}</span>
                        @if ($i === $peak && $b['value'] > 0)<span class="mb-1 text-xs font-semibold text-slate-700 tabular-nums">{{ $b['value'] }}</span>@endif
                        <span class="block w-full max-w-6 rounded-t" style="height: {{ $b['value'] ? max(2, $b['value'] / $maxSignup * 100) : 0 }}%; background: var(--chart-series)"></span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex gap-[2px] text-[10px] text-slate-500">
                @foreach ($buckets as $i => $b)<span class="min-w-0 flex-1 truncate text-center">{{ $i % max(1, intdiv(count($buckets), 8)) === 0 ? $b['label'] : '' }}</span>@endforeach
            </div>
            <details class="mt-3 text-sm"><summary class="cursor-pointer text-xs link">Voir les données</summary>
                <table class="table mt-2"><tbody class="divide-y divide-slate-100">@foreach ($buckets as $b)<tr><td>{{ $b['full'] }}</td><td class="text-right tabular-nums">{{ $b['value'] }}</td></tr>@endforeach</tbody></table>
            </details>
        </x-section>

        {{-- Parcours des nouveaux comptes --}}
        <x-section title="Parcours des nouveaux comptes">
            @php $top = max(1, $funnel[0][1]); @endphp
            <div class="space-y-3" data-funnel>
                @foreach ($funnel as $i => [$label, $value])
                    <div title="{{ $label }} : {{ $value }}">
                        <div class="mb-1 flex justify-between text-sm"><span>{{ $label }}</span><span class="font-semibold tabular-nums">{{ $value }}@if ($i) <span class="font-normal text-slate-500">· {{ $pct($value, $top) }}</span>@endif</span></div>
                        <div class="h-3 rounded-full bg-slate-100"><div class="h-3 rounded-full" style="width: {{ $value ? max(2, $value / $top * 100) : 0 }}%; background: var(--chart-series)"></div></div>
                    </div>
                @endforeach
            </div>
            <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-center text-sm">
                <div><dt class="text-xs text-slate-500">Essais démarrés</dt><dd class="font-semibold tabular-nums">{{ $trials['started'] }}</dd></div>
                <div><dt class="text-xs text-slate-500">Essais terminés</dt><dd class="font-semibold tabular-nums">{{ $trials['ended'] }}</dd></div>
                <div><dt class="text-xs text-slate-500">Devenus payants</dt><dd class="font-semibold tabular-nums">{{ $trials['converted'] }} <span class="font-normal text-slate-500">({{ $pct($trials['converted'], $trials['ended']) }})</span></dd></div>
            </dl>
        </x-section>

        {{-- Provenance : affiches, Instagram… --}}
        <x-section title="Provenance des inscriptions (QR codes et liens)" class="lg:col-span-2">
            @include('admin._acquisition', ['rows' => $acquisition])
        </x-section>

        {{-- Fonctions utilisées --}}
        <x-section title="Fonctions utilisées">
            <div class="space-y-2.5">
                @foreach ($hbar($usage) as $label => $row)
                    <div class="grid grid-cols-[10rem_1fr_auto] items-center gap-3 text-sm" title="{{ $label }} : {{ $row['value'] }}">
                        <span class="truncate">{{ $label }}</span>
                        <span class="h-3 rounded-r" style="width: {{ $row['value'] ? $row['width'] : 0 }}%; background: var(--chart-series)"></span>
                        <span class="w-12 text-right font-semibold tabular-nums">{{ number_format($row['value'], 0, ',', ' ') }}</span>
                    </div>
                @endforeach
            </div>
        </x-section>

        {{-- Propositions d'offre --}}
        <x-section title="Propositions d'offre cliquées">
            @if ($upsellClicks->isEmpty())
                <p class="text-sm text-slate-500">Aucun clic sur la période. Chaque clic sur une proposition (assistant, budget, documents, limites…) est compté ici, pour voir lesquelles donnent envie de passer à l'offre supérieure.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($hbar($upsellClicks) as $label => $row)
                        <div class="grid grid-cols-[12rem_1fr_auto] items-center gap-3 text-sm" title="{{ $label }} : {{ $row['value'] }}">
                            <span class="truncate">{{ $label }}</span>
                            <span class="h-3 rounded-r" style="width: {{ $row['width'] }}%; background: var(--chart-series)"></span>
                            <span class="w-10 text-right font-semibold tabular-nums">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-section>

        {{-- Départs et abandons --}}
        <x-section title="Résiliations et abandons">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between py-2"><dt>Abonnements terminés</dt><dd class="font-semibold tabular-nums">{{ $cancellations }}</dd></div>
                <div class="flex justify-between py-2"><dt>Résiliations programmées (en cours)</dt><dd class="font-semibold tabular-nums">{{ $scheduled }}</dd></div>
                <div class="flex justify-between py-2"><dt>Paiements commencés / abonnements créés</dt><dd class="font-semibold tabular-nums">{{ $checkouts['started'] }} / {{ $checkouts['completed'] }}</dd></div>
                <div class="flex justify-between py-2"><dt>Comptes sans visite depuis 30 jours</dt><dd class="font-semibold tabular-nums">{{ $dormant }} <span class="font-normal text-slate-500">({{ $pct($dormant, $kpis['users']) }})</span></dd></div>
            </dl>
            <p class="mt-2 text-xs text-slate-500">Nombres agrégés uniquement : aucune donnée personnelle n'est affichée.</p>
        </x-section>
    </div>
</x-layouts.app>
