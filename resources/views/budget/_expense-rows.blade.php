{{-- Dépenses (tableau, ordinateur et tablette). Ajoutées aussi par « Afficher plus » (ExpenseController::more). --}}
@php $eur ??= fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
@foreach ($expenses as $e)
    <tr class="cursor-pointer hover:bg-slate-50" onclick="if (! event.target.closest('a, button, form')) location.href = this.dataset.href" data-href="{{ route('budget.show', $e) }}">
        <td class="whitespace-nowrap">{{ $e->spent_on->format('d/m/Y') }}</td>
        <td>{{ $e->horse?->shortName() ?? '—' }}</td>
        <td><a href="{{ route('budget.show', $e) }}" class="font-medium text-slate-900 hover:underline" data-expense-link="{{ $e->id }}">{{ $e->category->name }}</a>@if ($e->comment)<span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($e->comment, 60) }}</span>@endif</td>
        <td class="hidden sm:table-cell">{{ $e->supplier }}</td>
        <td class="text-right font-medium whitespace-nowrap">{{ $eur($e->amount) }}@unless ($e->isPaid())<x-badge :color="$e->isOverdue() ? 'red' : 'amber'" class="ml-1">À payer</x-badge>@endunless</td>
        <td class="whitespace-nowrap">
            @foreach ($e->documents as $d)<x-receipt-link :expense="$e" :document="$d" :group="'receipt-'.$e->id.'-b'" class="text-xs">Justificatif</x-receipt-link> @endforeach
            @if ($e->author_id === auth()->id())<a href="{{ route('budget.edit', $e) }}" class="text-xs link">Modifier</a>@endif
        </td>
    </tr>
@endforeach
