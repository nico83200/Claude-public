@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app :title="'Relevé '.$statement->number">
    <x-page-header :title="'Relevé de pension · '.$statement->periodLabel()" :subtitle="$statement->organization->name.' · N° '.$statement->number" :back="$isStable ? route('boarding.index', ['mois' => $statement->period]) : route('budget.index')">
        <a href="{{ route('boarding.statement', [$statement, 'format' => 'pdf']) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> PDF</a>
    </x-page-header>
    <div class="card max-w-2xl p-5 sm:p-6">
        <div class="mb-3 flex items-center justify-between gap-2">
            <p class="text-sm text-slate-500">À l'attention de <strong class="text-slate-900">{{ $statement->owner_name }}</strong></p>
            <x-badge :color="['draft' => 'slate', 'sent' => 'amber', 'paid' => 'green'][$statement->status]">{{ $statement->status === 'paid' ? 'Payé le '.$statement->paid_on->format('d/m/Y') : \App\Models\BoardingStatement::STATUSES[$statement->status] }}</x-badge>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($statement->lines as $l)
                <li class="flex items-start justify-between gap-3 py-3 text-sm">
                    <span class="min-w-0"><span class="font-medium text-slate-900">{{ $l['horse'] }} · {{ $l['label'] }}</span><span class="block text-xs text-slate-500">{{ $l['detail'] }}@if ((float) $l['quantity'] != 1) · {{ rtrim(rtrim(number_format((float) $l['quantity'], 2, ',', ''), '0'), ',') }} × {{ $eur($l['unit_price']) }}@endif</span></span>
                    <span class="shrink-0 font-medium tabular-nums">{{ $eur($l['amount']) }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-2 flex items-center justify-between rounded-2xl bg-brand-50 px-4 py-3 text-brand-900"><span>Total</span><strong class="text-xl tabular-nums">{{ $eur($statement->total) }}</strong></p>
    </div>
</x-layouts.app>
