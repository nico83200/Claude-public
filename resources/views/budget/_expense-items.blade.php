{{-- Dépenses (téléphone) : lignes à glisser pour supprimer. Ajoutées aussi par « Afficher plus » (ExpenseController::more). --}}
@php $eur ??= fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
@foreach ($expenses as $e)
    <x-swipe.row :delete-url="$canDelete($e) ? route('budget.destroy', $e) : null" :text="'Dépense de '.$eur($e->amount).' supprimée'" rounded="" surface="bg-white">
    <div class="relative flex items-start gap-3 p-4 active:bg-slate-50">
        <div class="min-w-0 flex-1">
            <a href="{{ route('budget.show', $e) }}" class="absolute inset-0" aria-label="Détail : {{ $e->category->name }}, {{ $eur($e->amount) }}" data-expense-link="{{ $e->id }}"></a>
            <p class="font-medium text-slate-900">@if ($e->recurring_expense_id)<x-icon name="refresh" class="mr-0.5 inline size-3.5 text-slate-400" />@endif{{ $e->category->name }}@unless ($contextHorse)<span class="font-normal text-slate-500"> · {{ $e->horse?->shortName() ?? 'Espace' }}</span>@endunless</p>
            <p class="text-xs text-slate-500">{{ $e->spent_on->translatedFormat('j M Y') }}{{ $e->supplier ? ' · '.$e->supplier : '' }}</p>
            @if ($e->comment)<p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ $e->comment }}</p>@endif
            <p class="relative z-10 mt-1 flex w-fit gap-3 text-xs">
                @foreach ($e->documents as $d)<x-receipt-link :expense="$e" :document="$d">Justificatif</x-receipt-link>@endforeach
                @if ($e->author_id === auth()->id())<a href="{{ route('budget.edit', $e) }}" class="link">Modifier</a>@endif
            </p>
        </div>
        <p class="shrink-0 text-right font-semibold whitespace-nowrap">{{ $eur($e->amount) }}@unless ($e->isPaid())<x-badge :color="$e->isOverdue() ? 'red' : 'amber'" class="mt-1 block">À payer</x-badge>@endunless</p>
    </div>
    </x-swipe.row>
@endforeach
