<x-layouts.app title="Frais de cours">
    <x-page-header title="Frais de cours" subtitle="Vos cours, coachings et déplacements d'enseignants, comptés d'après leurs tarifs" :back="route('sessions.index')" />
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
        @forelse ($months as $ym => $list)
            <x-section :title="ucfirst(\Illuminate\Support\Carbon::parse($ym.'-01')->translatedFormat('F Y'))" :data-costs-month-block="$ym">
                <p class="-mt-2 mb-2 text-sm text-slate-500">{{ $money($list->sum('amount')) }}{{ $list->whereNull('paid_on')->isNotEmpty() ? ' · '.$money($list->whereNull('paid_on')->sum('amount')).' à payer' : ' · tout est payé' }}</p>
                @foreach ($list as $c)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-t border-slate-100 py-2.5 text-sm first:border-0" data-charge="{{ $c->id }}">
                        <span class="w-12 shrink-0 text-xs font-medium text-slate-500 uppercase">{{ $c->charged_on->translatedFormat('j M') }}</span>
                        <span class="min-w-0 flex-1">
                            @if ($c->sourceUrl())<a href="{{ $c->sourceUrl() }}" class="block font-medium text-slate-900 hover:underline">{{ $c->label }}</a>@else<span class="block font-medium text-slate-900">{{ $c->label }}</span>@endif
                            <span class="block text-xs text-slate-500">{{ $c->horse?->shortName() }}{{ $c->expense_id ? ' · aussi dans le budget du cheval' : '' }}</span>
                        </span>
                        <span class="font-semibold text-slate-900">{{ $money($c->amount) }}</span>
                        <form method="POST" action="{{ route('lesson-costs.paid', $c) }}">@csrf<input type="hidden" name="paid" value="{{ $c->paid_on ? 0 : 1 }}">
                            <button class="pill min-h-10 px-3 text-xs {{ $c->paid_on ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}" data-charge-toggle aria-label="{{ $c->paid_on ? 'Payé le '.$c->paid_on->format('d/m/Y').' : remettre à payer' : 'À payer : marquer payé' }}">{{ $c->paid_on ? 'Payé' : 'À payer' }}</button>
                        </form>
                    </div>
                @endforeach
            </x-section>
        @empty
            <x-empty title="Aucun frais de cours pour l'instant" icon="wallet">
                Ajoutez les tarifs de vos enseignants (Intervenants › l'enseignant › Tarifs) : le coût de chaque cours fait est compté ici, à votre nom. Vous pouvez aussi indiquer le prix sur la séance.
            </x-empty>
        @endforelse
    </div>
    <p class="mt-4 text-xs text-slate-500">Les frais à la charge du propriétaire du cheval figurent aussi dans son budget. Ceux d'une demi-pension ou d'un cavalier restent ici, visibles par lui seul.</p>
</x-layouts.app>
