@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app title="Budget">
    @php $newExpense = $creatableHorses->isNotEmpty() || $canOrgExpense; $exportOrg = $contextHorse?->organization ?? $currentOrganization; $singleHorse = $horses->count() < 2; @endphp
    @if ($contextHorse)
        <x-horse-header :horse="$contextHorse">
            @if ($newExpense)<button type="button" class="btn-primary" onclick="document.getElementById('new-expense').showModal()"><x-icon name="plus" class="size-4" /> Dépense</button>
                <button type="button" class="btn-secondary" onclick="document.getElementById('new-income').showModal()" data-new-income><x-icon name="plus" class="size-4" /> Recette</button>@endif
            <a href="{{ route('budget.export', ['pdf'] + request()->only('horse', 'year')) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> PDF <x-locked-mark :org="$exportOrg" feature="exports" /></a>
            <a href="{{ route('budget.export', ['csv'] + request()->only('horse', 'year')) }}" class="btn-ghost">CSV <x-locked-mark :org="$exportOrg" feature="exports" /></a>
        </x-horse-header>
    @else
        <x-page-header title="Budget" :subtitle="$periodLabel">
            <a href="{{ route('budget.export', ['pdf'] + request()->only('horse', 'year')) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> PDF <x-locked-mark :org="$exportOrg" feature="exports" /></a>
            <a href="{{ route('budget.export', ['csv'] + request()->only('horse', 'year')) }}" class="btn-ghost">CSV <x-locked-mark :org="$exportOrg" feature="exports" /></a>
            @if ($newExpense)<button type="button" class="btn-secondary" onclick="document.getElementById('new-income').showModal()" data-new-income><x-icon name="plus" /> Recette</button>
                <button type="button" class="btn-primary" onclick="document.getElementById('new-expense').showModal()"><x-icon name="plus" /> Dépense</button>@endif
        </x-page-header>
    @endif
    <x-upsell :offer="$upsell" class="mb-5" />
    {{-- Demi-pension (côté cavalier) : ses mensualités, sans accès au budget du cheval. --}}
    @php $riderFees = $contextHorse ? collect() : app(\App\Services\HalfLeaseFees::class)->forRider(auth()->user()); @endphp
    @if ($riderFees->isNotEmpty())
        <x-section title="Ma demi-pension" class="mb-4" :action="route('lesson-costs.index')" action-label="Mes frais" data-budget-half-lease>
            @foreach ($riderFees as $row)
                @php $last = $row['schedule']->first(); @endphp
                <a href="{{ route('half-leases.show', $row['lease']) }}" class="flex items-center justify-between gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0">
                    <span class="min-w-0"><span class="block font-medium text-slate-900">{{ $row['lease']->horse->shortName() }} · {{ $row['lease']->priceLabel() }}</span>
                        <span class="block text-xs text-slate-500">{{ $last ? $last['month'].' : '.($last['paid'] ? 'payée' : 'à régler') : 'Première mensualité à venir' }}{{ $row['next'] ? ' · prochaine le '.\App\Support\Fr::day($row['next']['due'], false) : '' }}</span></span>
                    @if ($last)<x-badge :color="$last['paid'] ? 'green' : 'amber'" class="shrink-0">{{ $eur($last['amount']) }}</x-badge>@endif
                </a>
            @endforeach
        </x-section>
    @endif
    @if ($newExpense)@include('budget._invoice-card')@endif
    <x-filters :fields="array_values(array_filter(['category', $contextHorse || $singleHorse ? null : 'horse']))" :keep="['horse' => $contextHorse?->id, 'year' => request('year')]">
        @unless ($contextHorse || $singleHorse)<x-select name="horse" label="Cheval" :options="$horses" :value="request('horse')" placeholder="Tous" />@endunless
        <x-select name="category" label="Catégorie" :options="$categories" :value="request('category')" placeholder="Toutes" />
    </x-filters>
    @php $years = collect(range(now()->year, now()->year - 5)); @endphp
    <nav class="no-scrollbar -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Année">
        @foreach ($years as $y)
            <a href="{{ request()->fullUrlWithQuery(['year' => $y, 'page' => null]) }}" @if ($y == $year) aria-current="page" @endif class="pill {{ $y == $year ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' }}">{{ $y }}</a>
        @endforeach
        <a href="{{ request()->fullUrlWithQuery(['year' => 'toutes', 'page' => null]) }}" @if (! $year) aria-current="page" @endif class="pill whitespace-nowrap {{ ! $year ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' }}" data-year-all>Toutes</a>
    </nav>
    @if ($unpaid->isNotEmpty())
        <section class="card mb-4 p-5 sm:p-6" aria-labelledby="to-pay">
            <div class="mb-2 flex items-baseline justify-between gap-2">
                <h2 id="to-pay" class="text-[17px] font-semibold">À payer</h2>
                <span class="text-sm font-semibold text-amber-700">{{ $eur($unpaid->sum('amount')) }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($unpaid as $e)
                    <li class="flex items-center gap-3 py-3">
                        <a href="{{ route('budget.show', $e) }}" class="min-w-0 flex-1" data-expense-link="{{ $e->id }}">
                            <span class="block font-medium text-slate-900">{{ $e->comment ?: $e->category->name }}@unless ($contextHorse || $singleHorse)<span class="font-normal text-slate-500"> · {{ $e->horse?->shortName() ?? 'Espace' }}</span>@endunless</span>
                            <span class="block text-xs {{ $e->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">{{ $e->isOverdue() ? 'En retard · ' : 'Échéance · ' }}{{ $e->spent_on->translatedFormat('j M Y') }} · {{ $eur($e->amount) }}</span>
                        </a>
                        @if ($canPay($e))
                            <form method="POST" action="{{ route('budget.pay', $e) }}">@csrf<input type="hidden" name="paid" value="1"><button class="btn-secondary min-h-9 px-4 text-sm"><x-icon name="check" class="size-4" /> Payé</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="card mb-4 grid grid-cols-3 divide-x divide-slate-100 py-4 text-center">
        @if (! $year || $year == now()->year)
            <div class="px-2"><p class="text-xs text-slate-500">Ce mois-ci</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['month']) }}</p></div>
        @else
            {{-- Année passée : « ce mois-ci » n'a pas de sens, on montre le mois le plus élevé. --}}
            @php $topMonth = collect($kpis['series'])->sortByDesc('value')->first(); @endphp
            <div class="px-2"><p class="text-xs text-slate-500">Mois le plus élevé</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($topMonth['value'] ?? 0) }}</p><p class="text-xs text-slate-500">{{ ($topMonth['value'] ?? 0) > 0 ? $topMonth['long'] : '—' }}</p></div>
        @endif
        <div class="px-2"><p class="text-xs text-slate-500">{{ $year ? 'Année '.$year : 'Toutes les dates' }}</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['year']) }}</p></div>
        <div class="px-2"><p class="text-xs text-slate-500">Moyenne par mois</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['per_month']) }}</p>
            @if ($kpis['fixed_monthly'] > 0)<p class="text-[11px] text-slate-500" data-fixed-monthly>dont {{ $eur($kpis['fixed_monthly']) }} de charges fixes</p>@endif</div>
    </div>
    @if ($incomes->isNotEmpty())
        {{-- Recettes de la période et solde (recettes − dépenses). --}}
        <div class="card mb-4 grid grid-cols-2 divide-x divide-slate-100 py-4 text-center" data-income-kpis>
            <div class="px-2"><p class="text-xs text-slate-500">Recettes · {{ $year ?: 'toutes les dates' }}</p><p class="mt-1 text-lg font-bold text-brand-800 sm:text-2xl">+ {{ $eur($kpis['income']) }}</p></div>
            <div class="px-2"><p class="text-xs text-slate-500">Solde (recettes − dépenses)</p><p class="mt-1 text-lg font-bold sm:text-2xl {{ $kpis['balance'] < 0 ? 'text-red-700' : 'text-brand-800' }}" data-balance>{{ $kpis['balance'] < 0 ? '− ' : '+ ' }}{{ $eur(abs($kpis['balance'])) }}</p>
                @if (request('category'))<p class="mt-0.5 text-[11px] text-slate-500">Toutes catégories de dépenses ({{ $eur($kpis['balance_expenses']) }})</p>@endif</div>
        </div>
    @endif
    @php
        $incCat = $kpis['income_by_category'];
        $maxCat = max(1, (float) $kpis['by_category']->max(), (float) $incCat->max());
        $hasIncome = $incCat->isNotEmpty();
        $maxHorse = max(1, (float) $kpis['by_horse']->max(fn ($h) => max($h['expense'], $h['income'])));
        $maxM = max(1, (float) collect($kpis['series'])->max(fn ($pt) => max($pt['value'], $pt['income'])));
        $zoom = 'block w-full cursor-zoom-in rounded-xl text-left focus-visible:outline-2 focus-visible:outline-brand-600';
        $signed = fn ($v) => ($v < 0 ? '− ' : '').$eur(abs($v));
    @endphp
    <div class="mb-4 grid gap-4 lg:grid-cols-3" data-budget-charts>
        <x-section title="Par catégorie">
            <x-slot:headerActions><button type="button" class="btn-ghost min-h-8 px-2 text-slate-500" onclick="document.getElementById('chart-category').showModal()" aria-label="Agrandir le graphique par catégorie"><x-icon name="expand" class="size-4" /></button></x-slot:headerActions>
            <button type="button" class="{{ $zoom }}" onclick="document.getElementById('chart-category').showModal()" data-chart-open="category">
            @if ($hasIncome)<p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Dépenses</p>@endif
            @forelse ($kpis['by_category'] as $name => $total)<div class="py-1 text-sm"><div class="flex justify-between"><span>{{ $name }}</span><span class="font-medium">{{ $eur($total) }}</span></div><div class="mt-0.5 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-brand-500" style="width: {{ round($total / $maxCat * 100) }}%"></div></div></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse
            @if ($hasIncome)
                <p class="mt-3 text-xs font-semibold tracking-wide text-slate-500 uppercase" data-income-categories>Recettes</p>
                @foreach ($incCat as $name => $total)<div class="py-1 text-sm"><div class="flex justify-between"><span>{{ $name }}</span><span class="font-medium text-brand-800">+ {{ $eur($total) }}</span></div><div class="mt-0.5 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-gold-400" style="width: {{ round($total / $maxCat * 100) }}%"></div></div></div>@endforeach
            @endif
            </button>
        </x-section>
        @unless ($contextHorse || $singleHorse)
        <x-section title="Coût par cheval">
            <x-slot:headerActions><button type="button" class="btn-ghost min-h-8 px-2 text-slate-500" onclick="document.getElementById('chart-horse').showModal()" aria-label="Agrandir le graphique par cheval"><x-icon name="expand" class="size-4" /></button></x-slot:headerActions>
            <button type="button" class="{{ $zoom }}" onclick="document.getElementById('chart-horse').showModal()" data-chart-open="horse">
            @forelse ($kpis['by_horse'] as $name => $h)
                <div class="py-1 text-sm" data-horse-cost><div class="flex justify-between gap-2"><span class="min-w-0 truncate">{{ $name }}</span><span class="shrink-0 font-medium">{{ $signed($h['net']) }}</span></div>
                    @if ($h['income'] > 0)<p class="text-xs text-slate-500">Dépenses {{ $eur($h['expense']) }} · recettes + {{ $eur($h['income']) }}</p>@endif</div>
            @empty<p class="text-sm text-slate-500">—</p>@endforelse
            @if ($kpis['by_horse']->contains(fn ($h) => $h['income'] > 0))<p class="mt-2 text-[11px] text-slate-500">Coût net : dépenses moins recettes.</p>@endif
            </button>
        </x-section>
        @endunless
        <x-section :title="$year ? 'Évolution mensuelle' : 'Évolution par année'">
            <x-slot:headerActions><button type="button" class="btn-ghost min-h-8 px-2 text-slate-500" onclick="document.getElementById('chart-month').showModal()" aria-label="Agrandir l'évolution"><x-icon name="expand" class="size-4" /></button></x-slot:headerActions>
            <button type="button" class="{{ $zoom }}" onclick="document.getElementById('chart-month').showModal()" data-chart-open="month">
            <div class="flex h-36 items-end gap-1" role="img" aria-label="{{ $year ? 'Dépenses et recettes par mois' : 'Dépenses et recettes par année' }}">
                @foreach ($kpis['series'] as $pt)<div class="flex flex-1 flex-col items-center gap-1" title="{{ $pt['long'] }} : dépenses {{ $eur($pt['value']) }}{{ $pt['income'] > 0 ? ' · recettes '.$eur($pt['income']) : '' }}"><div class="flex w-full items-end justify-center gap-px"><div class="w-full rounded-t bg-brand-500" style="height: {{ max(2, round($pt['value'] / $maxM * 110)) }}px"></div>@if ($hasIncome)<div class="w-full rounded-t bg-gold-400" style="height: {{ $pt['income'] > 0 ? max(2, round($pt['income'] / $maxM * 110)) : 0 }}px"></div>@endif</div><span class="text-[9px] text-slate-500">{{ $pt['short'] }}</span></div>@endforeach
            </div>
            @if ($hasIncome)<p class="mt-2 flex gap-3 text-[11px] text-slate-500"><span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm bg-brand-500"></span> Dépenses</span><span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm bg-gold-400"></span> Recettes</span></p>@endif
            </button>
        </x-section>
    </div>

    {{-- Graphiques agrandis --}}
    @foreach (['category' => $hasIncome ? 'Dépenses et recettes par catégorie' : 'Dépenses par catégorie', 'horse' => 'Coût par cheval', 'month' => $year ? 'Évolution mensuelle' : 'Évolution par année'] as $chart => $chartTitle)
        @continue($chart === 'horse' && ($contextHorse || $singleHorse))
        <dialog id="chart-{{ $chart }}" class="m-auto w-[calc(100%-1rem)] max-w-4xl rounded-3xl p-0 backdrop:bg-slate-900/50" onclick="if (event.target === this) this.close()" data-chart-dialog="{{ $chart }}">
            <div class="p-5 sm:p-8">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div><h2 class="text-xl font-semibold">{{ $chartTitle }}</h2><p class="text-sm text-slate-500">{{ $contextHorse?->shortName() ?? ($singleHorse ? $horses->first() : 'Tous les chevaux') }} · {{ $year ?: 'toutes les dates' }} · dépenses {{ $eur($kpis['year']) }}@if ($hasIncome) · recettes + {{ $eur($kpis['income']) }}@endif</p></div>
                    <button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button>
                </div>
                @if ($chart === 'month')
                    <div class="flex h-80 items-end gap-1.5 sm:gap-3" role="img" aria-label="{{ $year ? 'Dépenses et recettes par mois en '.$year : 'Dépenses et recettes par année' }}">
                        @foreach ($kpis['series'] as $pt)
                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                <span class="hidden text-xs font-medium whitespace-nowrap text-slate-600 sm:block">{{ $pt['value'] > 0 ? number_format($pt['value'], 0, ',', ' ').' €' : '' }}</span>
                                <div class="flex w-full items-end justify-center gap-0.5">
                                    <div class="w-full rounded-t-lg bg-brand-500" style="height: {{ max(3, round($pt['value'] / $maxM * 240)) }}px" title="{{ $pt['long'] }} : dépenses {{ $eur($pt['value']) }}"></div>
                                    @if ($hasIncome)<div class="w-full rounded-t-lg bg-gold-400" style="height: {{ $pt['income'] > 0 ? max(3, round($pt['income'] / $maxM * 240)) : 0 }}px" title="{{ $pt['long'] }} : recettes {{ $eur($pt['income']) }}"></div>@endif
                                </div>
                                <span class="text-[11px] text-slate-500 sm:text-sm">{{ $pt['short'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($hasIncome)<p class="mt-3 flex gap-4 text-sm text-slate-600"><span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-brand-500"></span> Dépenses</span><span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-gold-400"></span> Recettes</span></p>@endif
                    <div class="mt-6 grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                        @foreach ($kpis['series'] as $pt)<div class="flex justify-between gap-2 border-b border-slate-100 py-1"><span class="text-slate-600">{{ $pt['long'] }}</span><span class="text-right font-medium">{{ $eur($pt['value']) }}@if ($pt['income'] > 0)<span class="block text-xs text-brand-800">+ {{ $eur($pt['income']) }}</span>@endif</span></div>@endforeach
                    </div>
                @elseif ($chart === 'horse')
                    <div class="space-y-4">
                        @forelse ($kpis['by_horse'] as $name => $h)
                            <div>
                                <div class="flex items-baseline justify-between gap-3"><span class="font-medium">{{ $name }}</span><span class="text-sm text-slate-500">coût net <span class="font-semibold text-slate-900">{{ $signed($h['net']) }}</span></span></div>
                                <div class="mt-1.5 h-3 rounded-full bg-slate-100"><div class="h-3 rounded-full bg-brand-500" style="width: {{ max(1, round($h['expense'] / $maxHorse * 100)) }}%"></div></div>
                                @if ($h['income'] > 0)<div class="mt-1 h-3 rounded-full bg-slate-100"><div class="h-3 rounded-full bg-gold-400" style="width: {{ max(1, round($h['income'] / $maxHorse * 100)) }}%"></div></div>@endif
                                <p class="mt-1 text-xs text-slate-500">Dépenses {{ $eur($h['expense']) }}@if ($h['income'] > 0) · recettes + {{ $eur($h['income']) }}@endif</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Aucune dépense sur cette période.</p>
                        @endforelse
                    </div>
                @else
                    @php $sum = max(0.01, (float) $kpis['by_category']->sum()); $incSum = max(0.01, (float) $incCat->sum()); @endphp
                    <div class="space-y-4">
                        @if ($hasIncome)<p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Dépenses</p>@endif
                        @forelse ($kpis['by_category'] as $name => $total)
                            <div>
                                <div class="flex items-baseline justify-between gap-3"><span class="font-medium">{{ $name }}</span><span class="text-sm text-slate-500"><span class="font-semibold text-slate-900">{{ $eur($total) }}</span> · {{ number_format($total / $sum * 100, 0) }} %</span></div>
                                <div class="mt-1.5 h-4 rounded-full bg-slate-100"><div class="h-4 rounded-full bg-brand-500" style="width: {{ max(1, round($total / $maxCat * 100)) }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Aucune dépense sur cette période.</p>
                        @endforelse
                        @if ($hasIncome)
                            <p class="pt-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Recettes</p>
                            @foreach ($incCat as $name => $total)
                                <div>
                                    <div class="flex items-baseline justify-between gap-3"><span class="font-medium">{{ $name }}</span><span class="text-sm text-slate-500"><span class="font-semibold text-brand-800">+ {{ $eur($total) }}</span> · {{ number_format($total / $incSum * 100, 0) }} %</span></div>
                                    <div class="mt-1.5 h-4 rounded-full bg-slate-100"><div class="h-4 rounded-full bg-gold-400" style="width: {{ max(1, round($total / $maxCat * 100)) }}%"></div></div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endif
            </div>
        </dialog>
    @endforeach
    @if ($recurring->isNotEmpty())
        @php $endedCount = $recurring->filter->isEnded()->count(); @endphp
        <section class="card mb-4 p-5 sm:p-6" aria-labelledby="recurring-title" x-data="{ ended: false }">
            <h2 id="recurring-title" class="mb-2 text-[17px] font-semibold">Dépenses récurrentes</h2>
            {{-- Seules les récurrences en cours (ou suspendues) sont affichées ; les terminées sur demande. --}}
            @if ($endedCount === $recurring->count())<p class="py-2 text-sm text-slate-500" x-show="! ended">Aucune dépense récurrente en cours.</p>@endif
            <ul class="divide-y divide-slate-100">
                @foreach ($recurring->sortBy(fn ($r) => $r->isEnded() ? 1 : 0) as $r)
                    <li x-data="{ edit: false }" class="py-3" @if ($r->isEnded()) x-show="ended" x-cloak data-recurring-ended @else data-recurring-ongoing @endif>
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl {{ $r->paused_at ? 'bg-slate-100 text-slate-400' : 'bg-brand-50 text-brand-800' }}"><x-icon name="refresh" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-slate-900">{{ $r->label }}@unless ($contextHorse || $singleHorse)<span class="font-normal text-slate-500"> · {{ $r->horse?->shortName() ?? 'Espace' }}</span>@endunless</span>
                                <span class="block text-xs text-slate-500">
                                    {{ $eur($r->amount) }} {{ \App\Models\RecurringExpense::SHORT[$r->frequency] }} ·
                                    @if ($r->paused_at) Suspendue @elseif (! $r->isActive()) Terminée @else Prochaine le {{ $r->next_due_on->translatedFormat('j M Y') }} @endif
                                </span>
                            </span>
                            <button type="button" class="btn-ghost min-h-9 px-3 text-sm" @click="edit = ! edit" :aria-expanded="edit">Gérer</button>
                        </div>
                        <div x-show="edit" x-cloak class="mt-3 rounded-2xl bg-slate-50 p-4">
                            <form method="POST" action="{{ route('budget.recurring.update', $r) }}" class="grid grid-cols-2 gap-3">
                                @csrf @method('PUT')
                                <x-field name="label" label="Libellé" :value="$r->label" required class="col-span-2" :id="'rl'.$r->id" />
                                <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$r->amount" required inputmode="decimal" :id="'ra'.$r->id" />
                                <x-select name="frequency" label="Rythme" :options="\App\Models\RecurringExpense::FREQUENCIES" :value="$r->frequency" :id="'rf'.$r->id" />
                                <x-field name="next_due_on" type="date" label="Prochaine échéance" :value="$r->next_due_on->toDateString()" required :id="'rn'.$r->id" />
                                <x-field name="ends_on" type="date" label="Jusqu'au (facultatif)" :value="$r->ends_on?->toDateString()" :id="'re'.$r->id" />
                                <button class="btn-primary col-span-2">Enregistrer</button>
                            </form>
                            <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3">
                                <form method="POST" action="{{ route('budget.recurring.toggle', $r) }}">@csrf<button class="btn-secondary min-h-9 px-4 text-sm">{{ $r->paused_at ? 'Reprendre' : 'Suspendre' }}</button></form>
                                <x-confirm-delete :action="route('budget.recurring.destroy', $r)" message="Arrêter cette dépense récurrente ? Les échéances déjà enregistrées restent dans le budget." class="btn-ghost min-h-9 px-4 text-sm text-red-600">Arrêter</x-confirm-delete>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($endedCount)
                <button type="button" class="btn-ghost mt-2 min-h-9 px-3 text-sm" @click="ended = ! ended" :aria-expanded="ended" data-recurring-toggle>
                    <span x-text="ended ? 'Masquer les récurrences terminées' : 'Voir les récurrences terminées ({{ $endedCount }})'">Voir les récurrences terminées ({{ $endedCount }})</span>
                </button>
            @endif
        </section>
    @endif

    @if ($toReceive->isNotEmpty())
        <section class="card mb-4 p-5 sm:p-6" aria-labelledby="to-receive" data-to-receive>
            <div class="mb-2 flex items-baseline justify-between gap-2">
                <h2 id="to-receive" class="text-[17px] font-semibold">À recevoir</h2>
                <span class="text-sm font-semibold text-brand-800">+ {{ $eur($toReceive->sum('amount')) }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($toReceive as $i)
                    <li class="flex items-center gap-3 py-3" data-receivable="{{ $i->id }}">
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-slate-900">{{ $i->comment ?: $i->categoryLabel() }}@unless ($contextHorse || $singleHorse)<span class="font-normal text-slate-500"> · {{ $i->horse?->shortName() }}</span>@endunless</span>
                            <span class="block text-xs {{ $i->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">{{ $i->isOverdue() ? 'En retard · ' : 'Échéance · ' }}{{ $i->received_on->translatedFormat('j M Y') }} · + {{ $eur($i->amount) }}</span>
                        </span>
                        @if ($canEditIncome($i))
                            <form method="POST" action="{{ route('budget.incomes.receive', $i) }}">@csrf<input type="hidden" name="received" value="1"><button class="btn-secondary min-h-9 px-4 text-sm"><x-icon name="check" class="size-4" /> Reçue</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($recurringIncomes->isNotEmpty())
        @php $endedIncomes = $recurringIncomes->filter->isEnded()->count(); @endphp
        <section class="card mb-4 p-5 sm:p-6" aria-labelledby="recurring-incomes-title" x-data="{ ended: false }" data-recurring-incomes>
            <h2 id="recurring-incomes-title" class="mb-2 text-[17px] font-semibold">Recettes récurrentes</h2>
            @if ($endedIncomes === $recurringIncomes->count())<p class="py-2 text-sm text-slate-500" x-show="! ended">Aucune recette récurrente en cours.</p>@endif
            <ul class="divide-y divide-slate-100">
                @foreach ($recurringIncomes->sortBy(fn ($r) => $r->isEnded() ? 1 : 0) as $r)
                    <li x-data="{ edit: false }" class="py-3" @if ($r->isEnded()) x-show="ended" x-cloak data-recurring-income-ended @else data-recurring-income-ongoing @endif>
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl {{ $r->paused_at ? 'bg-slate-100 text-slate-400' : 'bg-gold-100 text-gold-700' }}"><x-icon name="refresh" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-slate-900">{{ $r->label }}@unless ($contextHorse || $singleHorse)<span class="font-normal text-slate-500"> · {{ $r->horse?->shortName() }}</span>@endunless</span>
                                <span class="block text-xs text-slate-500">+ {{ $eur($r->amount) }} {{ \App\Models\RecurringExpense::SHORT[$r->frequency] }} ·
                                    @if ($r->paused_at) Suspendue @elseif (! $r->isActive()) Terminée @else Prochaine le {{ $r->next_due_on->translatedFormat('j M Y') }}{{ $r->ends_on ? ', jusqu\'au '.$r->ends_on->translatedFormat('j M Y') : '' }} @endif</span>
                            </span>
                            @if ($canManageRecurringIncome($r))<button type="button" class="btn-ghost min-h-9 px-3 text-sm" @click="edit = ! edit" :aria-expanded="edit">Gérer</button>@endif
                        </div>
                        @if ($canManageRecurringIncome($r))
                        <div x-show="edit" x-cloak class="mt-3 rounded-2xl bg-slate-50 p-4">
                            <form method="POST" action="{{ route('budget.recurring-incomes.update', $r) }}" class="grid grid-cols-2 gap-3">
                                @csrf @method('PUT')
                                <x-field name="label" label="Libellé" :value="$r->label" required class="col-span-2" :id="'ril'.$r->id" />
                                <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$r->amount" required inputmode="decimal" :id="'ria'.$r->id" />
                                <x-select name="frequency" label="Rythme" :options="\App\Models\RecurringExpense::FREQUENCIES" :value="$r->frequency" :id="'rif'.$r->id" />
                                <x-field name="next_due_on" type="date" label="Prochaine échéance" :value="$r->next_due_on->toDateString()" required :id="'rin'.$r->id" />
                                <x-field name="ends_on" type="date" label="Jusqu'au (facultatif)" :value="$r->ends_on?->toDateString()" :id="'rie'.$r->id" />
                                <button class="btn-primary col-span-2">Enregistrer</button>
                            </form>
                            <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3">
                                <form method="POST" action="{{ route('budget.recurring-incomes.toggle', $r) }}">@csrf<button class="btn-secondary min-h-9 px-4 text-sm">{{ $r->paused_at ? 'Reprendre' : 'Suspendre' }}</button></form>
                                <x-confirm-delete :action="route('budget.recurring-incomes.destroy', $r)" message="Arrêter cette recette récurrente ? Les échéances déjà enregistrées restent dans le budget." class="btn-ghost min-h-9 px-4 text-sm text-red-600">Arrêter</x-confirm-delete>
                            </div>
                        </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($endedIncomes)
                <button type="button" class="btn-ghost mt-2 min-h-9 px-3 text-sm" @click="ended = ! ended" :aria-expanded="ended" data-recurring-incomes-toggle>
                    <span x-text="ended ? 'Masquer les recettes récurrentes terminées' : 'Voir les recettes récurrentes terminées ({{ $endedIncomes }})'">Voir les recettes récurrentes terminées ({{ $endedIncomes }})</span>
                </button>
            @endif
        </section>
    @endif

    @if ($incomes->isNotEmpty())
        <section class="card mb-4 p-5 sm:p-6" aria-labelledby="incomes-title" data-incomes x-data="{ all: false }">
            <div class="mb-2 flex items-baseline justify-between gap-2">
                <h2 id="incomes-title" class="text-[17px] font-semibold">Recettes</h2>
                <span class="text-sm font-semibold text-brand-800">+ {{ $eur($kpis['income']) }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($incomes as $i)
                    <li class="flex items-center gap-3 py-3" data-income="{{ $i->id }}" @if ($loop->index >= 5) x-show="all" x-cloak @endif>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-slate-900">{{ $i->comment ?: $i->categoryLabel() }}@unless ($contextHorse || $singleHorse)<span class="font-normal text-slate-500"> · {{ $i->horse?->shortName() }}</span>@endunless</span>
                            <span class="block text-xs text-slate-500">{{ $i->categoryLabel() }} · {{ $i->received_on->translatedFormat('j M Y') }}{{ $i->payer ? ' · '.$i->payer : '' }}</span>
                        </span>
                        <span class="shrink-0 text-right font-semibold whitespace-nowrap text-brand-800">+ {{ $eur($i->amount) }}@unless ($i->isReceived())<x-badge :color="$i->isOverdue() ? 'red' : 'amber'" class="ml-1">{{ $i->isOverdue() ? 'En retard' : 'À recevoir' }}</x-badge>@endunless</span>
                        @if ($canEditIncome($i))<a href="{{ route('budget.incomes.edit', array_filter(['income' => $i, 'retour_cheval' => $contextHorse?->id])) }}" class="btn-ghost size-10 shrink-0 px-0 text-slate-500" aria-label="Modifier la recette"><x-icon name="pencil" class="size-4" /></a>@endif
                    </li>
                @endforeach
            </ul>
            @if ($incomes->count() > 5)
                <button type="button" class="btn-ghost mt-2 min-h-9 px-3 text-sm" @click="all = ! all" :aria-expanded="all" data-incomes-toggle>
                    <span x-text="all ? 'Afficher moins' : 'Voir toutes les recettes ({{ $incomeCount }})'">Voir toutes les recettes ({{ $incomeCount }})</span>
                </button>
                @if ($incomeCount > $incomes->count())<p x-show="all" x-cloak class="mt-1 text-xs text-slate-500">Les {{ $incomes->count() }} plus récentes sont affichées ; l'export PDF ou CSV les contient toutes.</p>@endif
            @endif
        </section>
    @endif

    @if ($expenses->isEmpty())
        <x-empty :title="$year ? 'Aucune dépense en '.$year : 'Aucune dépense'" icon="wallet" />
    @else
        @php $anyDeletable = $expenses->contains(fn ($e) => $canDelete($e)); @endphp
        <x-swipe.list hint-key="jc-hint-swipe-expenses" :hint="$anyDeletable ? 'glissez une dépense vers la gauche pour la supprimer. Vous pourrez annuler juste après.' : null" class="sm:hidden">
        <div class="card divide-y divide-slate-100 overflow-hidden" data-expense-list>
            @include('budget._expense-items')
        </div>
        </x-swipe.list>
        <div class="table-wrap hidden sm:block"><table class="table">
            <thead><tr><th>Date</th><th>Cheval</th><th>Catégorie</th><th class="hidden sm:table-cell">Fournisseur</th><th class="text-right">Montant</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100" data-expense-table>
            @include('budget._expense-rows')
            </tbody>
        </table></div>
        {{-- 10 dépenses à la fois : les suivantes s'ajoutent sans recharger la page (lien classique sans JavaScript). --}}
        @if ($expenses->hasMorePages())
            <div class="mt-4 text-center" x-data="loadMore(@js(route('budget.more', array_merge(request()->query(), ['page' => $expenses->currentPage() + 1]))), { '[data-expense-list]': 'mobile', '[data-expense-table]': 'desktop' })" x-show="next">
                <a href="{{ $expenses->nextPageUrl() }}" class="btn-secondary" @click.prevent="load()" :aria-disabled="busy" data-load-more>
                    <x-icon name="plus" class="size-4" /> <span x-text="busy ? 'Chargement…' : 'Afficher {{ \App\Http\Controllers\ExpenseController::PER_PAGE }} dépenses de plus'">Afficher {{ \App\Http\Controllers\ExpenseController::PER_PAGE }} dépenses de plus</span>
                </a>
                <p x-show="error" x-cloak x-text="error" class="mt-2 text-sm text-red-700"></p>
            </div>
        @endif
    @endif

    @if ($newExpense)
    <dialog id="new-income" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40" data-income-dialog>
        <form method="POST" action="{{ route('budget.incomes.store') }}" class="space-y-3 p-5" x-data="{ recurring: @js((bool) old('income_recurring')) }">
            @csrf
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvelle recette</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            <p class="-mt-1 text-sm text-slate-500">Demi-pension, gains de concours, vente… : elle s'ajoute au solde du budget.</p>
            @php $ie = $errors->income; @endphp
            <div>
                <x-horse-select name="income_horse_id" :options="$creatableHorses" :value="request('horse')" :placeholder="$creatableHorses->count() > 1 ? 'Choisir…' : null" required id="i_horse" />
                @if ($ie->has('income_horse_id'))<p class="mt-1 text-sm text-red-600">{{ $ie->first('income_horse_id') }}</p>@endif
            </div>
            <div>
                <x-select name="income_category" label="Type de recette" :options="\App\Models\Income::CATEGORIES" required placeholder="Choisir…" id="i_category" />
                @if ($ie->has('income_category'))<p class="mt-1 text-sm text-red-600">{{ $ie->first('income_category') }}</p>@endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="i_received_on" x-text="recurring ? 'Première échéance' : 'Date'">Date</label><input id="i_received_on" type="date" name="income_received_on" value="{{ old('income_received_on', now()->toDateString()) }}" required class="input">@if ($ie->has('income_received_on'))<p class="mt-1 text-sm text-red-600">{{ $ie->first('income_received_on') }}</p>@endif</div>
                <div><label class="label" for="i_amount">Montant (€)<span class="text-red-600"> *</span></label><input id="i_amount" type="number" name="income_amount" step="0.01" min="0.01" inputmode="decimal" required value="{{ old('income_amount') }}" class="input">@if ($ie->has('income_amount'))<p class="mt-1 text-sm text-red-600">{{ $ie->first('income_amount') }}</p>@endif</div>
            </div>
            {{-- Recette qui revient (demi-pension chaque mois…), avec date de fin facultative. --}}
            <div class="rounded-2xl bg-slate-50 p-3">
                <label class="flex items-center gap-3 font-medium"><input type="checkbox" name="income_recurring" value="1" x-model="recurring" class="size-5 rounded" data-income-recurring> Recette récurrente (demi-pension…)</label>
                <div x-show="recurring" x-cloak class="mt-3 grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="label" for="i_label">Libellé</label><input id="i_label" name="income_label" maxlength="120" value="{{ old('income_label') }}" placeholder="ex. Demi-pension de Léa" class="input"></div>
                    <div><label class="label" for="i_frequency">Rythme</label><select id="i_frequency" name="income_frequency" class="input" data-native>@foreach (\App\Models\RecurringExpense::FREQUENCIES as $k => $l)<option value="{{ $k }}" @selected(old('income_frequency', 'monthly') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div><label class="label" for="i_ends_on">Jusqu'au (facultatif)</label><input id="i_ends_on" type="date" name="income_ends_on" value="{{ old('income_ends_on') }}" class="input">@if ($ie->has('income_ends_on'))<p class="mt-1 text-sm text-red-600">{{ $ie->first('income_ends_on') }}</p>@endif</div>
                </div>
            </div>
            <input type="hidden" name="income_received" value="0">
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="income_received" value="1" @checked(old('income_received', '1')) class="size-5 rounded"> <span x-text="recurring ? 'Échéances déjà passées reçues' : 'Déjà reçue'">Déjà reçue</span></label>
            <div><label class="label" for="i_payer">Versée par (facultatif)</label><input id="i_payer" name="income_payer" maxlength="150" value="{{ old('income_payer') }}" placeholder="Demi-pensionnaire, organisateur du concours…" class="input"></div>
            <div><label class="label" for="i_comment">Commentaire</label><textarea id="i_comment" name="income_comment" rows="2" maxlength="2000" class="input">{{ old('income_comment') }}</textarea></div>
            <button class="btn-primary w-full">Enregistrer la recette</button>
        </form>
    </dialog>
    @if ($errors->income->any())<script>document.getElementById('new-income').showModal()</script>@endif
    @endif

    <dialog id="new-expense" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('budget.store') }}" enctype="multipart/form-data" class="space-y-3 p-5" x-data="{ recurring: {{ old('recurring') ? 'true' : 'false' }}, cat: @js((string) old('expense_category_id', '')), careCats: @js($careCategoryIds), horse: @js((string) old('horse_id', request('horse', $creatableHorses->count() === 1 ? $creatableHorses->keys()->first() : ''))), openCares: @js($openCares), get cares() { return this.openCares[this.horse] || []; } }">
            @csrf
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvelle dépense</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            <x-horse-select name="horse_id" :options="$creatableHorses" :value="request('horse')" :placeholder="$creatableHorses->count() > 1 ? 'Choisir…' : null" required x-model="horse" />
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="f_spent_on" x-text="recurring ? 'Première échéance' : 'Date'">Date</label><input id="f_spent_on" type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" required class="input"></div>
                <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" required inputmode="decimal" />
            </div>
            <x-select name="expense_category_id" label="Catégorie" :options="$categories" required placeholder="Choisir…" x-model="cat" />
            {{-- Soins déjà notés pour ce cheval sans dépense : la dépense peut les compléter (seulement ceux-là). --}}
            <div class="rounded-2xl bg-brand-50 p-3 text-sm" x-show="! recurring && careCats.includes(cat) && cares.length" x-cloak data-care-link>
                <label class="label" for="f_care_link">Carnet de santé</label>
                <select id="f_care_link" name="care_link" class="input" :disabled="recurring || ! careCats.includes(cat) || ! cares.length" data-native>
                    <option value="new">Ajouter un nouveau soin au carnet</option>
                    <template x-for="c in cares" :key="c.id"><option :value="c.id" x-text="'Rattacher au soin du ' + c.label"></option></template>
                    <option value="none">Ne pas l'ajouter au carnet</option>
                </select>
                <p class="mt-1 text-xs text-brand-900/70">Seuls les soins qui n'ont pas encore de dépense sont proposés.</p>
            </div>
            <label class="flex items-start gap-3 rounded-2xl bg-brand-50 p-3 text-sm" x-show="! recurring && careCats.includes(cat) && ! cares.length" x-cloak data-add-care>
                <input type="hidden" name="add_care" value="0"><input type="checkbox" name="add_care" value="1" checked class="mt-0.5 size-5 rounded">
                <span><span class="block font-medium text-brand-900">Ajouter aussi le soin au carnet de santé</span><span class="block text-xs text-brand-900/70">Même date, intervenant et montant ; il restera synchronisé avec cette dépense.</span></span>
            </label>
            <div class="rounded-2xl bg-slate-50 p-3">
                <label class="flex items-center gap-3 font-medium"><input type="checkbox" name="recurring" value="1" x-model="recurring" class="size-5 rounded"> Dépense récurrente (pension, assurance…)</label>
                <div x-show="recurring" x-cloak class="mt-3 grid grid-cols-2 gap-3">
                    <x-field name="label" label="Libellé" placeholder="ex. Pension" class="col-span-2" maxlength="120" />
                    <x-select name="frequency" label="Rythme" :options="\App\Models\RecurringExpense::FREQUENCIES" value="monthly" />
                    <x-field name="ends_on" type="date" label="Jusqu'au (facultatif)" />
                </div>
            </div>
            <input type="hidden" name="paid" value="0">
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="paid" value="1" @checked(old('paid', '1')) class="size-5 rounded"> <span x-text="recurring ? 'Échéances déjà passées réglées' : 'Déjà payée'">Déjà payée</span></label>
            <x-field name="supplier" label="Fournisseur" />
            <x-professional-select label="Intervenant lié" :options="$professionals" />
            <x-textarea name="comment" label="Commentaire" rows="2" />
            <div><label class="label" for="receipt">Justificatif (facultatif)</label><input id="receipt" type="file" name="receipt" accept=".pdf,image/*" capture="environment" class="text-sm"></div>
            <button class="btn-primary w-full">Enregistrer</button>
        </form>
    </dialog>
    @if ($errors->any() || (request('nouvelle') === 'depense' && $newExpense))<script>document.getElementById('new-expense').showModal()</script>@endif
</x-layouts.app>
