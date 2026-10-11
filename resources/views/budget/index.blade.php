@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app title="Budget">
    @php $newExpense = $creatableHorses->isNotEmpty() || $canOrgExpense; @endphp
    @if ($contextHorse)
        <x-horse-header :horse="$contextHorse">
            @if ($newExpense)<button type="button" class="btn-primary" onclick="document.getElementById('new-expense').showModal()"><x-icon name="plus" class="size-4" /> Dépense</button>@endif
            <a href="{{ route('budget.export', ['pdf'] + request()->only('horse', 'year')) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> PDF</a>
            <a href="{{ route('budget.export', ['csv'] + request()->only('horse', 'year')) }}" class="btn-ghost">CSV</a>
        </x-horse-header>
    @else
        <x-page-header title="Budget" :subtitle="'Année '.$year">
            <a href="{{ route('budget.export', ['pdf'] + request()->only('horse', 'year')) }}" class="btn-ghost"><x-icon name="download" class="size-4" /> PDF</a>
            <a href="{{ route('budget.export', ['csv'] + request()->only('horse', 'year')) }}" class="btn-ghost">CSV</a>
            @if ($newExpense)<button type="button" class="btn-primary" onclick="document.getElementById('new-expense').showModal()"><x-icon name="plus" /> Dépense</button>@endif
        </x-page-header>
    @endif
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
    @if ($expenses->isEmpty())
        <x-empty title="Aucune dépense" icon="wallet" />
    @else
        <div class="card divide-y divide-slate-100 sm:hidden">
            @foreach ($expenses as $e)
                <div class="flex items-start gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-slate-900">{{ $e->category->name }}@unless ($contextHorse)<span class="font-normal text-slate-500"> · {{ $e->horse?->shortName() ?? 'Espace' }}</span>@endunless</p>
                        <p class="text-xs text-slate-500">{{ $e->spent_on->translatedFormat('j M Y') }}{{ $e->supplier ? ' · '.$e->supplier : '' }}</p>
                        @if ($e->comment)<p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ $e->comment }}</p>@endif
                        <p class="mt-1 flex gap-3 text-xs">
                            @foreach ($e->documents as $d)<a href="{{ route('budget.receipt', [$e, $d]) }}" class="link">Justificatif</a>@endforeach
                            @if ($e->author_id === auth()->id())<a href="{{ route('budget.edit', $e) }}" class="link">Modifier</a>@endif
                        </p>
                    </div>
                    <p class="shrink-0 font-semibold whitespace-nowrap">{{ $eur($e->amount) }}</p>
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
                    <td class="text-right font-medium whitespace-nowrap">{{ $eur($e->amount) }}</td>
                    <td class="whitespace-nowrap">
                        @foreach ($e->documents as $d)<a href="{{ route('budget.receipt', [$e, $d]) }}" class="text-xs link">Justificatif</a> @endforeach
                        @if ($e->author_id === auth()->id())<a href="{{ route('budget.edit', $e) }}" class="text-xs link">Modifier</a>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    @endif

    <dialog id="new-expense" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('budget.store') }}" enctype="multipart/form-data" class="space-y-3 p-5">
            @csrf
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Nouvelle dépense</h2><button type="button" class="btn-ghost px-2" onclick="this.closest('dialog').close()" aria-label="Fermer"><x-icon name="x" /></button></div>
            <x-select name="horse_id" label="Cheval" :options="$creatableHorses" :value="request('horse')" :placeholder="$canOrgExpense ? 'Dépense générale de l\'espace' : null" />
            <div class="grid grid-cols-2 gap-3">
                <x-field name="spent_on" type="date" label="Date" :value="now()->toDateString()" required />
                <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" required inputmode="decimal" />
            </div>
            <x-select name="expense_category_id" label="Catégorie" :options="$categories" required placeholder="Choisir…" />
            <x-field name="supplier" label="Fournisseur" />
            <x-select name="professional_id" label="Intervenant lié" :options="$professionals" placeholder="—" />
            <x-textarea name="comment" label="Commentaire" rows="2" />
            <div><label class="label" for="receipt">Justificatif (facultatif)</label><input id="receipt" type="file" name="receipt" accept=".pdf,image/*" capture="environment" class="text-sm"></div>
            <button class="btn-primary w-full">Enregistrer</button>
        </form>
    </dialog>
    @if ($errors->any())<script>document.getElementById('new-expense').showModal()</script>@endif
</x-layouts.app>
