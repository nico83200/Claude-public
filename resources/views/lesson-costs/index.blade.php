<x-layouts.app title="Mes frais">
    <x-page-header title="Mes frais" :subtitle="$halfLeases->isNotEmpty() ? 'Demi-pension, cours, coachings et déplacements d\'enseignants' : 'Vos cours, coachings et déplacements d\'enseignants, comptés d\'après leurs tarifs'" :back="route('sessions.index')" />
    @php $money = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
    <div class="mb-4 grid grid-cols-2 gap-3">
        <div class="card p-4"><p class="text-xs text-slate-500">Ce mois-ci</p><p class="mt-1 text-2xl font-bold text-slate-900" data-costs-month>{{ $money($thisMonth) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">À payer</p><p class="mt-1 text-2xl font-bold {{ $unpaid > 0 ? 'text-amber-700' : 'text-slate-900' }}" data-costs-unpaid>{{ $money($unpaid) }}</p></div>
    </div>
    @if ($horses->count() > 1)
        <div class="no-scrollbar -mx-4 mb-3 flex gap-2 overflow-x-auto px-4 py-1 sm:mx-0 sm:px-1" data-costs-horses>
            <a href="{{ route('lesson-costs.index') }}" class="pill min-h-11 shrink-0 px-3 text-sm {{ $horse ? 'bg-white text-slate-700 ring-1 ring-slate-200' : 'bg-brand-50 font-semibold text-brand-900 ring-2 ring-brand-500' }}">Tous</a>
            @foreach ($horses as $h)
                <a href="{{ route('lesson-costs.index', ['horse' => $h->id]) }}" class="pill min-h-11 shrink-0 px-3 text-sm {{ $horse === $h->id ? 'bg-brand-50 font-semibold text-brand-900 ring-2 ring-brand-500' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">{{ $h->shortName() }}</a>
            @endforeach
        </div>
    @endif
    <div class="space-y-4">
        @if ($halfLeases->isNotEmpty())
            {{-- Demi-pension : mensualités du budget du propriétaire (notées reçues par lui) ou des relevés de l'écurie. --}}
            <x-section title="Demi-pension" data-costs-half-lease>
                @foreach ($halfLeases as $row)
                    @php $hl = $row['lease']; @endphp
                    <div class="border-t border-slate-100 py-2.5 first:border-0">
                        <a href="{{ route('half-leases.show', $hl) }}" class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0"><span class="block font-medium text-slate-900">{{ $hl->horse->shortName() }} · {{ $hl->priceLabel() }}</span>
                                <span class="block text-xs text-slate-500">{{ $row['next'] ? 'Prochaine mensualité le '.\App\Support\Fr::day($row['next']['due'], false) : ($hl->status === 'ended' ? 'Terminée' : 'Avec le relevé de l\'écurie') }}</span></span>
                            <x-icon name="chevron" class="size-4 shrink-0 text-slate-400" />
                        </a>
                        @foreach ($row['schedule']->take(4) as $fee)
                            <div class="flex items-center justify-between gap-2 py-1 pl-1 text-sm">
                                <span class="text-slate-600">{{ $fee['month'] }}</span>
                                <span class="flex shrink-0 items-center gap-2"><span class="whitespace-nowrap tabular-nums">{{ $money($fee['amount']) }}</span><x-badge :color="$fee['paid'] ? 'green' : 'amber'">{{ $fee['paid'] ? 'Payée' : 'À régler' }}</x-badge></span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </x-section>
        @endif
        @if ($byCoach->isNotEmpty())
            {{-- Mes cours cette année, par enseignant. --}}
            <x-section :title="'Mes cours en '.now()->year" data-costs-coaches>
                @foreach ($byCoach as $name => $row)
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0">
                        <span class="min-w-0"><span class="block font-medium text-slate-900">{{ $name }}</span><span class="block text-xs text-slate-500">{{ $row['count'] }} cours · dernier le {{ $row['last']->translatedFormat('j F') }}</span></span>
                        <span class="shrink-0 whitespace-nowrap font-semibold tabular-nums">{{ $money($row['total']) }}</span>
                    </div>
                @endforeach
            </x-section>
        @endif
        @if ($statements->isNotEmpty())
            <x-section title="Relevés reçus" data-costs-statements>
                @foreach ($statements as $st)
                    <a href="{{ route('boarding.statement', $st) }}" class="flex items-center justify-between gap-3 border-t border-slate-100 py-2.5 text-sm first:border-0">
                        <span class="min-w-0"><span class="block font-medium text-slate-900">{{ $st->title() }} · {{ $st->periodLabel() }}</span><span class="block text-xs text-slate-500">{{ $st->organization?->name }} · N° {{ $st->number }}</span></span>
                        <span class="flex shrink-0 items-center gap-2"><span class="whitespace-nowrap tabular-nums">{{ $money($st->total) }}</span><x-badge :color="$st->status === 'paid' ? 'green' : 'amber'">{{ $st->status === 'paid' ? 'Payé' : 'À payer' }}</x-badge></span>
                    </a>
                @endforeach
            </x-section>
        @endif
        {{-- Cartes et forfaits : les cours faits avec l'enseignant y sont décomptés au lieu d'être comptés au tarif. --}}
        <x-section title="Cartes et forfaits" data-packages>
            @forelse ($packages as $pkg)
                @php
                    $used = $usage[$pkg->id] ?? 0;
                    $ended = $pkg->ends_on && $pkg->ends_on->lt(today());
                @endphp
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 py-2.5 text-sm first:border-0 {{ $ended ? 'opacity-60' : '' }}" data-package="{{ $pkg->id }}">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium text-slate-900">{{ $pkg->title() }}</span>
                        <span class="block text-xs text-slate-500">{{ $pkg->horse?->shortName() }} · {{ $money($pkg->price) }}{{ $pkg->kind === 'monthly' ? ' par mois' : '' }} · depuis le {{ $pkg->starts_on->format('d/m/Y') }}{{ $pkg->ends_on ? ($ended ? ', terminé le ' : ', jusqu\'au ').$pkg->ends_on->format('d/m/Y') : '' }}</span>
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $used >= $pkg->lessons ? 'bg-amber-100 text-amber-800' : 'bg-brand-50 text-brand-900' }}" data-package-usage>
                        {{ $pkg->kind === 'card' ? $used.' / '.$pkg->lessons.' cours utilisés' : $used.' / '.$pkg->lessons.' ce mois-ci' }}
                    </span>
                    <form method="POST" action="{{ route('lesson-packages.destroy', $pkg) }}" data-confirm="Supprimer {{ $pkg->kind === 'card' ? 'cette carte' : 'ce forfait' }} ? Les cours qu'il couvrait seront de nouveau comptés au tarif." data-confirm-ok="Supprimer">
                        @csrf @method('DELETE')<button class="btn-ghost min-h-10 px-2 text-red-600" aria-label="Supprimer"><x-icon name="trash" class="size-4" /></button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-slate-500">Carte de cours (des cours payés d'avance) ou forfait mensuel avec un enseignant : les cours faits y sont décomptés, au lieu d'être comptés au tarif.</p>
            @endforelse
            @if ($instructors->isNotEmpty() && $packageHorses->isNotEmpty())
                <details class="group mt-3 rounded-2xl bg-slate-50 p-3" @if ($errors->hasAny(['professional_id', 'horse_id', 'kind', 'lessons', 'price', 'starts_on', 'ends_on'])) open @endif data-package-form>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-medium text-slate-700">Ajouter une carte ou un forfait<x-icon name="chevron" class="size-4 transition group-open:rotate-90" /></summary>
                    <form method="POST" action="{{ route('lesson-packages.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2" x-data="{ kind: @js(old('kind', 'card')) }">
                        @csrf
                        <x-select name="kind" label="Type" :options="\App\Models\LessonPackage::KINDS" value="card" x-model="kind" />
                        <x-select name="professional_id" label="Enseignant" :options="$instructors" required />
                        <x-horse-select name="horse_id" :options="$packageHorses" :value="$horse ?? array_key_first($packageHorses->all())" required />
                        <div><x-field name="lessons" type="number" min="1" max="200" required label="Nombre de cours" />
                            <p class="mt-1 text-xs text-slate-500" x-text="kind === 'card' ? 'Cours compris dans la carte.' : 'Cours compris chaque mois.'"></p></div>
                        <div><x-field name="price" type="number" step="0.01" min="0" required label="Prix (€)" inputmode="decimal" />
                            <p class="mt-1 text-xs text-slate-500" x-text="kind === 'card' ? 'Prix de la carte.' : 'Prix par mois.'"></p></div>
                        <x-field name="starts_on" type="date" label="À partir du" :value="today()->toDateString()" required />
                        <div><x-field name="ends_on" type="date" label="Jusqu'au (facultatif)" />
                            <p class="mt-1 text-xs text-slate-500" x-text="kind === 'card' ? 'Date de validité de la carte.' : 'Fin du forfait.'"></p></div>
                        <div class="sm:col-span-2"><button class="btn-primary">Ajouter</button></div>
                    </form>
                </details>
            @elseif ($instructors->isEmpty())
                <p class="mt-2 text-xs text-slate-500">Ajoutez d'abord votre enseignant dans <a href="{{ route('professionals.index') }}" class="link">Intervenants</a> (type « Enseignant »).</p>
            @endif
        </x-section>
        @forelse ($months as $ym => $list)
            <x-section :title="ucfirst(\Illuminate\Support\Carbon::parse($ym.'-01')->translatedFormat('F Y'))" :data-costs-month-block="$ym">
                <p class="-mt-2 mb-2 text-sm text-slate-500">{{ $money($list->sum('amount')) }}{{ $list->whereNull('paid_on')->isNotEmpty() ? ' · '.$money($list->whereNull('paid_on')->sum('amount')).' à payer' : ' · tout est payé' }}</p>
                @foreach ($list as $c)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-slate-100 py-2.5 text-sm first:border-0" data-charge="{{ $c->id }}">
                        <span class="w-12 shrink-0 text-xs font-medium text-slate-500 uppercase">{{ $c->charged_on->translatedFormat('j M') }}</span>
                        <span class="min-w-0 flex-1">
                            @if ($c->sourceUrl())<a href="{{ $c->sourceUrl() }}" class="block font-medium text-slate-900 hover:underline">{{ $c->label }}</a>@else<span class="block font-medium text-slate-900">{{ $c->label }}</span>@endif
                            <span class="block text-xs text-slate-500">{{ $c->horse?->shortName() }}{{ $c->expense_id ? ' · aussi dans le budget du cheval' : '' }}{{ $c->statement && $c->statement->status !== 'draft' ? ' · relevé n° '.$c->statement->number : '' }}</span>
                        </span>
                        <span class="font-semibold text-slate-900">{{ $money($c->amount) }}</span>
                        <form method="POST" action="{{ route('lesson-costs.paid', $c) }}">@csrf<input type="hidden" name="paid" value="{{ $c->paid_on ? 0 : 1 }}">
                            <button class="pill min-h-10 px-3 text-xs {{ $c->paid_on ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}" data-charge-toggle aria-label="{{ $c->paid_on ? 'Payé le '.$c->paid_on->format('d/m/Y').' : remettre à payer' : 'À payer : marquer payé' }}">{{ $c->paid_on ? 'Payé' : 'À payer' }}</button>
                        </form>
                    </div>
                @endforeach
            </x-section>
        @empty
            <x-empty title="Aucun frais de cours pour l'instant" icon="wallet" data-costs-empty>
                Ajoutez les tarifs de vos enseignants (Intervenants › l'enseignant › Tarifs) : le coût de chaque cours fait est compté ici, à votre nom. Vous pouvez aussi indiquer le prix sur la séance.
            </x-empty>
        @endforelse
    </div>
    <p class="mt-4 text-xs text-slate-500">Les frais à la charge du propriétaire du cheval figurent aussi dans son budget. Ceux d'une demi-pension ou d'un cavalier restent ici, visibles par lui seul.</p>
</x-layouts.app>
