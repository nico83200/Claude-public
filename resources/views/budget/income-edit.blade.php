<x-layouts.app title="Modifier la recette">
    <x-page-header title="Modifier la recette" :subtitle="$income->horse?->shortName()" :back="route('budget.index', array_filter(['horse' => request('retour_cheval')]))">
        <x-confirm-delete :action="route('budget.incomes.destroy', array_filter(['income' => $income, 'retour_cheval' => request('retour_cheval')]))" message="Supprimer cette recette ?" />
    </x-page-header>
    <form method="POST" action="{{ route('budget.incomes.update', $income) }}" class="max-w-lg space-y-3" data-income-edit>
        @csrf @method('PUT')
        @if (request('retour_cheval'))<input type="hidden" name="retour_cheval" value="{{ request('retour_cheval') }}">@endif
        @php $ie = $errors->income; @endphp
        @if ($ie->any())<div class="rounded-2xl bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($ie->all() as $m)<p>{{ $m }}</p>@endforeach</div>@endif
        <x-select name="income_horse_id" label="Cheval" :options="$horses" :value="$income->horse_id" required />
        <x-select name="income_category" label="Type de recette" :options="\App\Models\Income::CATEGORIES" :value="$income->category" required />
        <div class="grid grid-cols-2 gap-3">
            <x-field name="income_received_on" type="date" label="Date" :value="$income->received_on->toDateString()" required />
            <x-field name="income_amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$income->amount" required inputmode="decimal" />
        </div>
        <x-field name="income_payer" label="Versée par" :value="$income->payer" maxlength="150" />
        <x-textarea name="income_comment" label="Commentaire" :value="$income->comment" rows="2" />
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
