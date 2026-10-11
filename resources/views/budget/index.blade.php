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
        <x-page-header title="Budget" :subtitle="'Année '.$year">
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
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-slate-900">{{ $e->comment ?: $e->category->name }}@unless ($contextHorse)<span class="font-normal text-slate-500"> · {{ $e->horse?->shortName() ?? 'Espace' }}</span>@endunless</span>
                            <span class="block text-xs {{ $e->isOverdue() ? 'font-medium text-red-600' : 'text-slate-500' }}">{{ $e->isOverdue() ? 'En retard · ' : 'Échéance · ' }}{{ $e->spent_on->translatedFormat('j M Y') }} · {{ $eur($e->amount) }}</span>
                        </span>
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
        <div class="px-2"><p class="text-xs text-slate-500">Année {{ $year }}</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['year']) }}</p></div>
        <div class="px-2"><p class="text-xs text-slate-500">Par mois</p><p class="mt-1 text-lg font-bold sm:text-2xl">{{ $eur($kpis['year'] / max(1, $year == now()->year ? now()->month : 12)) }}</p></div>
    </div>
    <div class="mb-4 grid gap-4 lg:grid-cols-3">
        <x-section title="Par catégorie">
            @php $maxCat = max(1, (float) $kpis['by_category']->max()); @endphp
            @forelse ($kpis['by_category'] as $name => $total)<div class="py-1 text-sm"><div class="flex justify-between"><span>{{ $name }}</span><span class="font-medium">{{ $eur($total) }}</span></div><div class="mt-0.5 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-brand-500" style="width: {{ round($total / $maxCat * 100) }}%"></div></div></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse
        </x-section>
        @unless ($contextHorse)
        <x-section title="Coût par cheval">
            @forelse ($kpis['by_horse'] as $name => $total)<div class="flex justify-between py-1 text-sm"><span>{{ $name }}</span><span class="font-medium">{{ $eur($total) }}</span></div>@empty<p class="text-sm text-slate-500">—</p>@endforelse
        </x-section>
        @endunless
        <x-section title="Évolution mensuelle">
            @php $maxM = max(1, (float) $kpis['by_month']->max()); @endphp
            <div class="flex h-36 items-end gap-1" role="img" aria-label="Dépenses par mois">
                @for ($m = 1; $m <= 12; $m++)<div class="flex flex-1 flex-col items-center gap-1" title="{{ \Illuminate\Support\Carbon::create(null, $m)->translatedFormat('F') }} : {{ $eur($kpis['by_month'][$m] ?? 0) }}"><div class="w-full rounded-t bg-brand-500" style="height: {{ max(2, round(($kpis['by_month'][$m] ?? 0) / $maxM * 110)) }}px"></div><span class="text-[9px] text-slate-500">{{ mb_substr(\Illuminate\Support\Carbon::create(null, $m)->translatedFormat('M'), 0, 3) }}</span></div>@endfor
            </div>
        </x-section>
    </div>
    @if ($recurring->isNotEmpty())
        <section class="card mb-4 p-5 sm:p-6" aria-labelledby="recurring-title">
            <h2 id="recurring-title" class="mb-2 text-[17px] font-semibold">Dépenses récurrentes</h2>
            <ul class="divide-y divide-slate-100">
                @foreach ($recurring as $r)
                    <li x-data="{ edit: false }" class="py-3">
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
        </section>
    @endif

    @if ($expenses->isEmpty())
        <x-empty title="Aucune dépense" icon="wallet" />
    @else
        <div class="card divide-y divide-slate-100 sm:hidden">
            @foreach ($expenses as $e)
                <div class="flex items-start gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-slate-900">@if ($e->recurring_expense_id)<x-icon name="refresh" class="mr-0.5 inline size-3.5 text-slate-400" />@endif{{ $e->category->name }}@unless ($contextHorse)<span class="font-normal text-slate-500"> · {{ $e->horse?->shortName() ?? 'Espace' }}</span>@endunless</p>
                        <p class="text-xs text-slate-500">{{ $e->spent_on->translatedFormat('j M Y') }}{{ $e->supplier ? ' · '.$e->supplier : '' }}</p>
                        @if ($e->comment)<p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ $e->comment }}</p>@endif
                        <p class="mt-1 flex gap-3 text-xs">
                            @foreach ($e->documents as $d)<a href="{{ route('budget.receipt', [$e, $d]) }}" class="link" @if ($d->isImage()) data-lightbox="receipt-{{ $e->id }}" data-caption="Justificatif · {{ $e->category->name }}" @endif>Justificatif</a>@endforeach
                            @if ($e->author_id === auth()->id())<a href="{{ route('budget.edit', $e) }}" class="link">Modifier</a>@endif
                        </p>
                    </div>
                    <p class="shrink-0 text-right font-semibold whitespace-nowrap">{{ $eur($e->amount) }}@unless ($e->isPaid())<x-badge :color="$e->isOverdue() ? 'red' : 'amber'" class="mt-1 block">À payer</x-badge>@endunless</p>
                </div>
            @endforeach
        </div>
        <div class="table-wrap hidden sm:block"><table class="table">
            <thead><tr><th>Date</th><th>Cheval</th><th>Catégorie</th><th class="hidden sm:table-cell">Fournisseur</th><th class="text-right">Montant</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($expenses as $e)
                <tr>
                    <td class="whitespace-nowrap">{{ $e->spent_on->format('d/m/Y') }}</td>
                    <td>{{ $e->horse?->shortName() ?? '—' }}</td>
                    <td>{{ $e->category->name }}@if ($e->comment)<span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($e->comment, 60) }}</span>@endif</td>
                    <td class="hidden sm:table-cell">{{ $e->supplier }}</td>
                    <td class="text-right font-medium whitespace-nowrap">{{ $eur($e->amount) }}@unless ($e->isPaid())<x-badge :color="$e->isOverdue() ? 'red' : 'amber'" class="ml-1">À payer</x-badge>@endunless</td>
                    <td class="whitespace-nowrap">
                        @foreach ($e->documents as $d)<a href="{{ route('budget.receipt', [$e, $d]) }}" class="text-xs link" @if ($d->isImage()) data-lightbox="receipt-{{ $e->id }}-b" data-caption="Justificatif · {{ $e->category->name }}" @endif>Justificatif</a> @endforeach
                        @if ($e->author_id === auth()->id())<a href="{{ route('budget.edit', $e) }}" class="text-xs link">Modifier</a>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    @endif

    <dialog id="new-expense" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('budget.store') }}" enctype="multipart/form-data" class="space-y-3 p-5" x-data="{ recurring: {{ old('recurring') ? 'true' : 'false' }} }">
            @csrf
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvelle dépense</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            <x-select name="horse_id" label="Cheval" :options="$creatableHorses" :value="request('horse')" :placeholder="$creatableHorses->count() > 1 ? 'Choisir…' : null" required />
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="f_spent_on" x-text="recurring ? 'Première échéance' : 'Date'">Date</label><input id="f_spent_on" type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" required class="input"></div>
                <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" required inputmode="decimal" />
            </div>
            <x-select name="expense_category_id" label="Catégorie" :options="$categories" required placeholder="Choisir…" />
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
