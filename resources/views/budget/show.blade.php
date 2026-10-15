@php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ').' €'; @endphp
<x-layouts.app :title="$expense->category->name.' · Budget'">
    <x-page-header :title="$expense->comment ?: $expense->category->name" :subtitle="($expense->horse?->shortName() ?? 'Espace').' · '.$expense->spent_on->translatedFormat('j F Y')" :back="route('budget.index', array_filter(['horse' => $expense->horse_id, 'year' => $expense->spent_on->year]))">
        @if ($canEdit)<a href="{{ route('budget.edit', ['expense' => $expense, 'retour' => '/budget/'.$expense->id]) }}" class="btn-primary" data-expense-edit><x-icon name="pencil" class="size-4" /> Modifier</a>@endif
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-3" data-expense-detail>
        <x-section class="lg:col-span-2">
            <p class="text-3xl font-bold">{{ $eur($expense->amount) }}</p>
            <p class="mt-2">@if ($expense->isPaid())<x-badge color="green">Payée{{ $expense->paid_on ? ' le '.$expense->paid_on->format('d/m/Y') : '' }}</x-badge>@else<x-badge :color="$expense->isOverdue() ? 'red' : 'amber'">{{ $expense->isOverdue() ? 'En retard' : 'À payer' }}</x-badge>@endif</p>
            <div class="mt-4"></div>
            <x-dl :items="[
                'Catégorie' => $expense->category->name,
                'Cheval' => $expense->horse?->shortName(),
                'Date' => $expense->spent_on->format('d/m/Y'),
                'Fournisseur' => $expense->supplier,
                'Intervenant' => $expense->professional?->fullName(),
                'Commentaire' => $expense->comment,
                'Dépense récurrente' => $expense->recurring ? $expense->recurring->label.' · '.(\App\Models\RecurringExpense::FREQUENCIES[$expense->recurring->frequency] ?? $expense->recurring->frequency) : null,
                'Saisie par' => $expense->author?->name,
            ]" />
            @if ($canEdit)
                <a href="{{ route('budget.edit', ['expense' => $expense, 'retour' => '/budget/'.$expense->id]) }}" class="btn-secondary mt-4 w-full justify-center sm:w-auto" data-expense-edit-bottom><x-icon name="pencil" class="size-4" /> Modifier la dépense</a>
            @endif
            @if ($canEdit && ! $expense->isPaid())
                <form method="POST" action="{{ route('budget.pay', $expense) }}" class="mt-4">@csrf<input type="hidden" name="paid" value="1"><button class="btn-primary"><x-icon name="check" class="size-4" /> Marquer comme payée</button></form>
            @endif
        </x-section>
        <div class="space-y-4">
            <x-section title="Justificatifs">
                @forelse ($expense->documents as $d)
                    <x-receipt-link :expense="$expense" :document="$d" class="block py-1 text-sm">{{ $d->original_name ?: 'Justificatif' }}</x-receipt-link>
                @empty
                    <p class="text-sm text-slate-500">Aucun justificatif.@if ($canEdit) <a href="{{ route('budget.edit', $expense) }}" class="link">Ajouter</a>@endif</p>
                @endforelse
            </x-section>
            @if ($expense->professional)
                <x-section title="Intervenant"><a href="{{ route('professionals.show', $expense->professional) }}" class="text-sm link">{{ $expense->professional->fullName() }}</a></x-section>
            @endif
            @if ($canEdit)
                <x-confirm-delete :action="route('budget.destroy', $expense)" message="Supprimer cette dépense ?" :check="$linkedCare ? 'with_care' : null" :check-label="$linkedCare ? 'Supprimer aussi le soin associé du '.$linkedCare->performed_at->format('d/m/Y') : null" class="btn-ghost text-sm text-red-600"><x-icon name="trash" class="size-4" /> Supprimer la dépense</x-confirm-delete>
            @endif
        </div>
    </div>
</x-layouts.app>
