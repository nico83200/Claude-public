<x-layouts.app title="Modifier la dépense">
    <x-page-header title="Modifier la dépense" :back="route('budget.index')">
        <x-confirm-delete :action="route('budget.destroy', $expense)" message="Supprimer cette dépense ?" :check="$linkedCare ? 'with_care' : null" :check-label="$linkedCare ? 'Supprimer aussi le soin associé du '.$linkedCare->performed_at->format('d/m/Y') : null" />
    </x-page-header>
    <form method="POST" action="{{ route('budget.update', $expense) }}" enctype="multipart/form-data" class="max-w-lg space-y-3">
        @csrf @method('PUT')
        <x-horse-select name="horse_id" :options="$horses" :value="$expense->horse_id" placeholder="Choisir…" required />
        <div class="grid grid-cols-2 gap-3">
            <x-field name="spent_on" type="date" label="Date" :value="$expense->spent_on->toDateString()" required />
            <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$expense->amount" required />
        </div>
        <x-select name="expense_category_id" label="Catégorie" :options="$categories" :value="$expense->expense_category_id" required />
        <x-field name="supplier" label="Fournisseur" :value="$expense->supplier" />
        <x-textarea name="comment" label="Commentaire" :value="$expense->comment" />
        <div><label class="label" for="receipt">Ajouter un justificatif</label><input id="receipt" type="file" name="receipt" accept=".pdf,image/*" class="text-sm"></div>
        @if (str_starts_with((string) request('retour'), '/') && ! str_starts_with((string) request('retour'), '//'))<input type="hidden" name="return_to" value="{{ request('retour') }}">@endif
        @if ($expense->recurring)
            {{-- Échéance d'une dépense récurrente : portée de la modification. --}}
            <fieldset class="rounded-2xl border border-slate-200 p-4" data-recurring-scope>
                <legend class="px-1 text-sm font-semibold text-slate-800">Dépense récurrente « {{ $expense->recurring->label }} » : que modifier ?</legend>
                @foreach (['one' => ['Cette échéance uniquement', 'Les autres échéances ne changent pas.'], 'following' => ['Cette échéance et les suivantes', 'Les échéances après le '.$expense->spent_on->format('d/m/Y').' et celles à venir.'], 'all' => ['Toute la série', 'Toutes les échéances, passées comprises, et celles à venir.']] as $value => [$label, $help])
                    <label class="flex min-h-11 items-start gap-3 py-1.5 text-sm">
                        <input type="radio" name="scope" value="{{ $value }}" @checked(old('scope', 'one') === $value) class="mt-0.5 size-5 border-slate-300 text-brand-700">
                        <span><span class="block font-medium text-slate-900">{{ $label }}</span><span class="block text-xs text-slate-500">{{ $help }}</span></span>
                    </label>
                @endforeach
                <p class="mt-1 text-xs text-slate-500">La date ne change que pour cette échéance ; le rythme se règle dans « Dépenses récurrentes » du budget.</p>
            </fieldset>
        @endif
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
