<x-layouts.app title="Modifier la dépense">
    <x-page-header title="Modifier la dépense" :back="route('budget.index')">
        <x-confirm-delete :action="route('budget.destroy', $expense)" message="Supprimer cette dépense ?" />
    </x-page-header>
    <form method="POST" action="{{ route('budget.update', $expense) }}" enctype="multipart/form-data" class="max-w-lg space-y-3">
        @csrf @method('PUT')
        <x-select name="horse_id" label="Cheval" :options="$horses" :value="$expense->horse_id" placeholder="Dépense générale" />
        <div class="grid grid-cols-2 gap-3">
            <x-field name="spent_on" type="date" label="Date" :value="$expense->spent_on->toDateString()" required />
            <x-field name="amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$expense->amount" required />
        </div>
        <x-select name="expense_category_id" label="Catégorie" :options="$categories" :value="$expense->expense_category_id" required />
        <x-field name="supplier" label="Fournisseur" :value="$expense->supplier" />
        <x-textarea name="comment" label="Commentaire" :value="$expense->comment" />
        <div><label class="label" for="receipt">Ajouter un justificatif</label><input id="receipt" type="file" name="receipt" accept=".pdf,image/*" class="text-sm"></div>
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
