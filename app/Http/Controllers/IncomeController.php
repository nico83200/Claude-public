<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\Income;
use App\Services\Audit;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recettes du budget (demi-pension, gains de concours, vente…). Mêmes droits que les dépenses :
 * visibles avec expenses.view sur le cheval, saisies avec expenses.create, modifiées par leur auteur
 * ou par le propriétaire / gérant du cheval.
 */
class IncomeController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $horse = Horse::findOrFail($data['horse_id']);
        $this->authorizeHorse($horse, Perm::EXPENSES_CREATE);
        $this->requireFeature($horse->organization, 'budget', 'Suivi des dépenses');

        $income = new Income($data);
        $income->organization_id = $horse->organization_id;
        $income->author_id = $request->user()->id;
        $income->save();
        Audit::log('income.created', $income, ['amount' => (string) $income->amount], $horse->organization_id);

        return back()->with('success', 'Recette de '.number_format((float) $income->amount, 2, ',', ' ').' € enregistrée.');
    }

    public function edit(Request $request, Income $income, HorseAccess $access)
    {
        $this->authorizeEdit($request, $income);

        return view('budget.income-edit', [
            'income' => $income->load('horse'),
            'horses' => Horse::whereIn('id', $access->horseIdsWith($request->user(), Perm::EXPENSES_CREATE))->whereNull('archived_at')->orderBy('official_name')->pluck('official_name', 'id'),
        ]);
    }

    public function update(Request $request, Income $income)
    {
        $this->authorizeEdit($request, $income);
        $data = $this->validated($request);
        $horse = Horse::findOrFail($data['horse_id']);
        $this->authorizeHorse($horse, Perm::EXPENSES_CREATE);
        $income->fill($data);
        $income->organization_id = $horse->organization_id;
        $income->save();
        Audit::log('income.updated', $income, array_keys($income->getChanges()), $horse->organization_id);

        return redirect()->route('budget.index', array_filter(['horse' => $request->integer('retour_cheval') ?: null]))->with('success', 'Recette modifiée.');
    }

    public function destroy(Request $request, Income $income)
    {
        $this->authorizeEdit($request, $income);
        $income->delete();
        Audit::log('income.deleted', $income, ['amount' => (string) $income->amount], $income->organization_id);

        return redirect()->route('budget.index', array_filter(['horse' => $request->integer('retour_cheval') ?: null]))->with('success', 'Recette supprimée.');
    }

    /** Recettes visibles : chevaux dont l'utilisateur voit les dépenses. */
    public static function visibleQuery(Request $request, HorseAccess $access): Builder
    {
        return Income::query()->whereIn('incomes.horse_id', $access->horseIdsWith($request->user(), Perm::EXPENSES_VIEW));
    }

    public static function canEdit(Request $request, Income $income): bool
    {
        $access = app(HorseAccess::class);
        $user = $request->user();

        return $income->horse && $access->can($user, $income->horse, Perm::EXPENSES_CREATE)
            && ($income->author_id === $user->id || $access->can($user, $income->horse, Perm::HORSE_MANAGE));
    }

    private function authorizeEdit(Request $request, Income $income): void
    {
        abort_unless(self::canEdit($request, $income), 403, 'Vous ne pouvez pas modifier cette recette.');
    }

    private function validated(Request $request): array
    {
        // Erreurs dans leur propre sac : la fenêtre « Nouvelle recette » se rouvre, pas celle des dépenses.
        return $request->validateWithBag('income', [
            'horse_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys(Income::CATEGORIES))],
            'received_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'payer' => ['nullable', 'string', 'max:150'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['category.required' => 'Choisissez le type de recette.']);
    }
}
