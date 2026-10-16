<x-layouts.app title="Modifier la recette">
    <x-page-header title="Modifier la recette" :subtitle="$income->horse?->shortName()" :back="route('budget.index', array_filter(['horse' => request('retour_cheval')]))">
        <x-confirm-delete :action="route('budget.incomes.destroy', array_filter(['income' => $income, 'retour_cheval' => request('retour_cheval')]))" message="Supprimer cette recette ?" />
    </x-page-header>
    <form method="POST" action="{{ route('budget.incomes.update', $income) }}" class="max-w-lg space-y-3" data-income-edit>
        @csrf @method('PUT')
        @if (request('retour_cheval'))<input type="hidden" name="retour_cheval" value="{{ request('retour_cheval') }}">@endif
        @php $ie = $errors->income; @endphp
        @if ($ie->any())<div class="rounded-2xl bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($ie->all() as $m)<p>{{ $m }}</p>@endforeach</div>@endif
        <x-horse-select name="income_horse_id" :options="$horses" :value="$income->horse_id" required />
        <x-select name="income_category" label="Type de recette" :options="\App\Models\Income::CATEGORIES" :value="$income->category" required />
        <div class="grid grid-cols-2 gap-3">
            <x-field name="income_received_on" type="date" label="Date" :value="$income->received_on->toDateString()" required />
            <x-field name="income_amount" type="number" step="0.01" min="0.01" label="Montant (€)" :value="$income->amount" required inputmode="decimal" />
        </div>
        <div class="rounded-2xl bg-slate-50 p-3" x-data="{ received: @js((bool) old('income_received', $income->isReceived())) }">
            <input type="hidden" name="income_received" value="0">
            <label class="flex items-center gap-3 font-medium"><input type="checkbox" name="income_received" value="1" x-model="received" class="size-5 rounded" data-income-received> Reçue</label>
            <div x-show="received" x-cloak class="mt-3"><x-field name="income_collected_on" type="date" label="Reçue le" :value="$income->collected_on?->toDateString()" /></div>
        </div>
        <x-field name="income_payer" label="Versée par" :value="$income->payer" maxlength="150" />
        <x-textarea name="income_comment" label="Commentaire" :value="$income->comment" rows="2" />
        <button class="btn-primary">Enregistrer</button>
    </form>
</x-layouts.app>
