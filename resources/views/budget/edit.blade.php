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
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
