@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app title="Budget">
    @php $newExpense = $creatableHorses->isNotEmpty() || $canOrgExpense; $exportOrg = $contextHorse?->organization ?? $currentOrganization; @endphp
    @if ($contextHorse)
        <x-horse-header :horse="$contextHorse">
            @if ($newExpense)<button type="button" class="btn-primary" onclick="document.getElementById('new-expense').showModal()"><x-icon name="plus" class="size-4" /> Dépense</button>@endif
            <a href="{{ route('budget.export', ['pdf'] + request()->only('horse', 'year')) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> PDF <x-locked-mark :org="$exportOrg" feature="exports" /></a>
            <a href="{{ route('budget.export', ['csv'] + request()->only('horse', 'year')) }}" class="btn-ghost">CSV <x-locked-mark :org="$exportOrg" feature="exports" /></a>
        </x-horse-header>
    @else
        <x-page-header title="Budget" :subtitle="$periodLabel">
            <a href="{{ route('budget.export', ['pdf'] + request()->only('horse', 'year')) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> PDF <x-locked-mark :org="$exportOrg" feature="exports" /></a>
            <a href="{{ route('budget.export', ['csv'] + request()->only('horse', 'year')) }}" class="btn-ghost">CSV <x-locked-mark :org="$exportOrg" feature="exports" /></a>
            @if ($newExpense)<button type="button" class="btn-primary" onclick="document.getElementById('new-expense').showModal()"><x-icon name="plus" /> Dépense</button>@endif
        </x-page-header>
    @endif
    <x-upsell :offer="$upsell" class="mb-5" />
    @if ($newExpense)@include('budget._invoice-card')@endif
    <x-filters :fields="array_values(array_filter(['category', $contextHorse ? null : 'horse']))" :keep="['horse' => $contextHorse?->id, 'year' => request('year')]">
        @unless ($contextHorse)<x-select name="horse" label="Cheval" :options="$horses" :value="request('horse')" placeholder="Tous" />@endunless
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
                            <span class="block font-medium text-slate-900">{{ $e->comment ?: $e->category->name }}@unless ($contextHorse)<span class="font-normal text-slate-500"> · {{ $e->horse?->shortName() ?? 'Espace' }}</span>@endunless</span>
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
        <div class="px-2"><p class="text-xs text-slate-500">Ce mois-ci</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['month']) }}</p></div>
        <div class="px-2"><p class="text-xs text-slate-500">{{ $year ? 'Année '.$year : 'Toutes les dates' }}</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['year']) }}</p></div>
        <div class="px-2"><p class="text-xs text-slate-500">Par mois</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['per_month']) }}</p></div>
    </div>
    @php
        $maxCat = max(1, (float) $kpis['by_category']->max());
        $maxHorse = max(1, (float) $kpis['by_horse']->max());
        $maxM = max(1, (float) collect($kpis['series'])->max('value'));
        $totalCat = max(0.01, (float) $kpis['by_category']->sum());
        $zoom = 'block w-full cursor-zoom-in rounded-xl text-left focus-visible:outline-2 focus-visible:outline-brand-600';
    @endphp
    <div class="mb-4 grid gap-4 lg:grid-cols-3" data-budget-charts>
        <x-section title="Par catégorie">
            <x-slot:headerActions><button type="button" class="btn-ghost min-h-8 px-2 text-slate-500" onclick="document.getElementById('chart-category').showModal()" aria-label="Agrandir le graphique par catégorie"><x-icon name="expand" class="size-4" /></button></x-slot:headerActions>
            <button type="button" class="{{ $zoom }}" onclick="document.getElementById('chart-category').showModal()" data-chart-open="category">
            @forelse ($kpis['by_category'] as $name => $total)<div class="py-1 text-sm"><div class="flex justify-between"><span>{{ $name }}</span><span class="font-medium">{{ $eur($total) }}</span></div><div class="mt-0.5 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-brand-500" style="width: {{ round($total / $maxCat * 100) }}%"></div></div></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse
            </button>
        </x-section>
        @unless ($contextHorse)
        <x-section title="Coût par cheval">
            <x-slot:headerActions><button type="button" class="btn-ghost min-h-8 px-2 text-slate-500" onclick="document.getElementById('chart-horse').showModal()" aria-label="Agrandir le graphique par cheval"><x-icon name="expand" class="size-4" /></button></x-slot:headerActions>
            <button type="button" class="{{ $zoom }}" onclick="document.getElementById('chart-horse').showModal()" data-chart-open="horse">
            @forelse ($kpis['by_horse'] as $name => $total)<div class="flex justify-between py-1 text-sm"><span>{{ $name }}</span><span class="font-medium">{{ $eur($total) }}</span></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse
            </button>
        </x-section>
        @endunless
        <x-section :title="$year ? 'Évolution mensuelle' : 'Évolution par année'">
            <x-slot:headerActions><button type="button" class="btn-ghost min-h-8 px-2 text-slate-500" onclick="document.getElementById('chart-month').showModal()" aria-label="Agrandir l'évolution"><x-icon name="expand" class="size-4" /></button></x-slot:headerActions>
            <button type="button" class="{{ $zoom }}" onclick="document.getElementById('chart-month').showModal()" data-chart-open="month">
            <div class="flex h-36 items-end gap-1" role="img" aria-label="{{ $year ? 'Dépenses par mois' : 'Dépenses par année' }}">
                @foreach ($kpis['series'] as $pt)<div class="flex flex-1 flex-col items-center gap-1" title="{{ $pt['long'] }} : {{ $eur($pt['value']) }}"><div class="w-full rounded-t bg-brand-500" style="height: {{ max(2, round($pt['value'] / $maxM * 110)) }}px"></div><span class="text-[9px] text-slate-500">{{ $pt['short'] }}</span></div>@endforeach
            </div>
            </button>
        </x-section>
    </div>

    {{-- Graphiques agrandis --}}
    @foreach (['category' => 'Dépenses par catégorie', 'horse' => 'Coût par cheval', 'month' => $year ? 'Évolution mensuelle' : 'Évolution par année'] as $chart => $chartTitle)
        @continue($chart === 'horse' && $contextHorse)
        <dialog id="chart-{{ $chart }}" class="m-auto w-[calc(100%-1rem)] max-w-4xl rounded-3xl p-0 backdrop:bg-slate-900/50" onclick="if (event.target === this) this.close()" data-chart-dialog="{{ $chart }}">
            <div class="p-5 sm:p-8">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div><h2 class="text-xl font-semibold">{{ $chartTitle }}</h2><p class="text-sm text-slate-500">{{ $contextHorse?->shortName() ?? 'Tous les chevaux' }} · {{ $year ?: 'toutes les dates' }} · total {{ $eur($kpis['year']) }}</p></div>
                    <button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button>
                </div>
                @if ($chart === 'month')
                    <div class="flex h-80 items-end gap-1.5 sm:gap-3" role="img" aria-label="{{ $year ? 'Dépenses par mois en '.$year : 'Dépenses par année' }}">
                        @foreach ($kpis['series'] as $pt)
                            <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                <span class="hidden text-xs font-medium whitespace-nowrap text-slate-600 sm:block">{{ $pt['value'] > 0 ? number_format($pt['value'], 0, ',', ' ').' €' : '' }}</span>
                                <div class="w-full rounded-t-lg bg-brand-500" style="height: {{ max(3, round($pt['value'] / $maxM * 240)) }}px" title="{{ $pt['long'] }} : {{ $eur($pt['value']) }}"></div>
                                <span class="text-[11px] text-slate-500 sm:text-sm">{{ $pt['short'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                        @foreach ($kpis['series'] as $pt)<div class="flex justify-between border-b border-slate-100 py-1"><span class="text-slate-600">{{ $pt['long'] }}</span><span class="font-medium">{{ $eur($pt['value']) }}</span></div>@endforeach
                    </div>
                @else
                    @php $rows = $chart === 'category' ? $kpis['by_category'] : $kpis['by_horse']; $max = $chart === 'category' ? $maxCat : $maxHorse; $sum = max(0.01, (float) $rows->sum()); @endphp
                    <div class="space-y-4">
                        @forelse ($rows as $name => $total)
                            <div>
                                <div class="flex items-baseline justify-between gap-3"><span class="font-medium">{{ $name }}</span><span class="text-sm text-slate-500"><span class="font-semibold text-slate-900">{{ $eur($total) }}</span> · {{ number_format($total / $sum * 100, 0) }} %</span></div>
                                <div class="mt-1.5 h-4 rounded-full bg-slate-100"><div class="h-4 rounded-full bg-brand-500" style="width: {{ max(1, round($total / $max * 100)) }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Aucune dépense sur cette période.</p>
                        @endforelse
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
                                <span class="block font-medium text-slate-900">{{ $r->label }}@unless ($contextHorse)<span class="font-normal text-slate-500"> · {{ $r->horse?->shortName() ?? 'Espace' }}</span>@endunless</span>
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

    @if ($expenses->isEmpty())
        <x-empty title="Aucune dépense" icon="wallet" />
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

    <dialog id="new-expense" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('budget.store') }}" enctype="multipart/form-data" class="space-y-3 p-5" x-data="{ recurring: {{ old('recurring') ? 'true' : 'false' }}, cat: @js((string) old('expense_category_id', '')), careCats: @js($careCategoryIds), horse: @js((string) old('horse_id', request('horse', $creatableHorses->count() === 1 ? $creatableHorses->keys()->first() : ''))), openCares: @js($openCares), get cares() { return this.openCares[this.horse] || []; } }">
            @csrf
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvelle dépense</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            <x-select name="horse_id" label="Cheval" :options="$creatableHorses" :value="request('horse')" :placeholder="$creatableHorses->count() > 1 ? 'Choisir…' : null" required x-model="horse" />
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
    @if ($errors->any())<script>document.getElementById('new-expense').showModal()</script>@endif
</x-layouts.app>
