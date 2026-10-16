<?php

namespace App\Http\Controllers;

use App\Models\Horse;
use App\Models\Income;
use App\Models\RecurringExpense;
use App\Models\RecurringIncome;
use App\Services\Audit;
use App\Services\HorseAccess;
use App\Services\RecurringIncomes;
use App\Support\Perm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Recette récurrente (demi-pension chaque mois…) : les échéances passées sont créées tout de suite.
        if ($request->boolean('income_recurring')) {
            $extra = $request->validateWithBag('income', [
                'income_frequency' => ['required', 'in:'.implode(',', array_keys(RecurringExpense::FREQUENCIES))],
                'income_label' => ['nullable', 'string', 'max:120'],
                'income_ends_on' => ['nullable', 'date', 'after_or_equal:income_received_on'],
            ], [], ['income_frequency' => 'rythme', 'income_label' => 'libellé', 'income_ends_on' => 'date de fin']);
            $rec = new RecurringIncome([
                'horse_id' => $horse->id, 'category' => $data['category'], 'label' => ($extra['income_label'] ?? null) ?: Income::CATEGORIES[$data['category']],
                'amount' => $data['amount'], 'frequency' => $extra['income_frequency'], 'starts_on' => $data['received_on'], 'next_due_on' => $data['received_on'],
                'ends_on' => $extra['income_ends_on'] ?? null, 'payer' => $data['payer'] ?? null, 'comment' => $data['comment'] ?? null,
            ]);
            $rec->organization_id = $horse->organization_id;
            $rec->author_id = $request->user()->id;
            $rec->save();
            app(RecurringIncomes::class)->generate($rec, max: RecurringIncomes::MAX_AT_CREATION);
            // Échéances antérieures ou égales à aujourd'hui : reçues si l'utilisateur l'indique.
            if ($request->boolean('income_received')) {
                $rec->occurrences()->whereNull('collected_on')->where('received_on', '<=', today()->toDateString())->update(['collected_on' => DB::raw('received_on')]);
            }
            Audit::log('income.recurring_created', $rec, ['amount' => (string) $rec->amount], $horse->organization_id);

            return back()->with('success', 'Recette récurrente créée : '.mb_strtolower(RecurringExpense::FREQUENCIES[$rec->frequency]).', prochaine échéance le '.$rec->fresh()->next_due_on->translatedFormat('j F Y').'.');
        }

        $income = new Income($data);
        $income->organization_id = $horse->organization_id;
        $income->author_id = $request->user()->id;
        $income->collected_on = $request->boolean('income_received', true) ? $data['received_on'] : null;
        $income->save();
        Audit::log('income.created', $income, ['amount' => (string) $income->amount], $horse->organization_id);

        return back()->with('success', 'Recette de '.number_format((float) $income->amount, 2, ',', ' ').' € enregistrée'.($income->isReceived() ? '.' : ', à recevoir.'));
    }

    public function edit(Request $request, Income $income, HorseAccess $access)
    {
        $this->authorizeEdit($request, $income);

        return view('budget.income-edit', [
            'income' => $income->load('horse'),
            // Une recette reste dans son espace : seuls les chevaux de cet espace sont proposés.
            'horses' => Horse::whereIn('id', $access->horseIdsWith($request->user(), Perm::EXPENSES_CREATE))->where('organization_id', $income->organization_id)
                ->whereNull('archived_at')->orderBy('official_name')->pluck('official_name', 'id'),
        ]);
    }

    public function update(Request $request, Income $income)
    {
        $this->authorizeEdit($request, $income);
        $data = $this->validated($request);
        $horse = Horse::findOrFail($data['horse_id']);
        $this->authorizeHorse($horse, Perm::EXPENSES_CREATE);
        abort_unless($horse->organization_id === $income->organization_id, 422, 'Une recette ne peut pas changer d\'espace.');
        $this->requireFeature($horse->organization, 'budget', 'Suivi des dépenses');
        $income->fill($data);
        // « Reçue » modifiable (erreur de saisie) : date de réception choisie, sinon la date de la recette.
        $received = $request->validateWithBag('income', ['income_collected_on' => ['nullable', 'date', 'before_or_equal:today']], [], ['income_collected_on' => 'date de réception']);
        $income->collected_on = $request->boolean('income_received') ? (($received['income_collected_on'] ?? null) ?: ($income->collected_on ?? min($data['received_on'], today()->toDateString()))) : null;
        $income->save();
        Audit::log('income.updated', $income, array_keys($income->getChanges()), $horse->organization_id);

        return redirect()->route('budget.index', array_filter(['horse' => $request->integer('retour_cheval') ?: null]))->with('success', 'Recette modifiée.');
    }

    /** « Reçue » (ou remise « à recevoir »), comme le paiement d'une dépense. */
    public function receive(Request $request, Income $income)
    {
        $this->authorizeEdit($request, $income);
        $data = $request->validate(['received' => ['required', 'boolean'], 'collected_on' => ['nullable', 'date', 'before_or_equal:today']]);
        $income->update(['collected_on' => $request->boolean('received') ? ($data['collected_on'] ?? today()->toDateString()) : null]);
        Audit::log($income->collected_on ? 'income.received' : 'income.unreceived', $income, ['amount' => (string) $income->amount], $income->organization_id);

        return back()->with('success', $income->collected_on ? 'Recette notée comme reçue.' : 'Recette remise « à recevoir ».');
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
        return Income::query()->whereIn('incomes.horse_id', $access->horseIdsWith($request->user(), Perm::EXPENSES_VIEW))
            ->whereIn('incomes.horse_id', Horse::query()->select('horses.id'));
    }

    public static function canEdit(Request $request, Income $income): bool
    {
        $access = app(HorseAccess::class);
        $user = $request->user();
        $income->loadMissing('horse.organization');

        return $income->horse && $access->can($user, $income->horse, Perm::EXPENSES_CREATE)
            && ($income->author_id === $user->id || $access->can($user, $income->horse, Perm::HORSE_MANAGE));
    }

    private function authorizeEdit(Request $request, Income $income): void
    {
        abort_unless(self::canEdit($request, $income), 403, 'Vous ne pouvez pas modifier cette recette.');
    }

    private function validated(Request $request): array
    {
        // Champs préfixés (income_…) : ils ne remplissent pas, après une erreur, la fenêtre des dépenses ni les filtres.
        // Erreurs dans leur propre sac : la fenêtre « Nouvelle recette » se rouvre, pas celle des dépenses.
        $data = $request->validateWithBag('income', [
            'income_horse_id' => ['required', 'integer'],
            'income_category' => ['required', Rule::in(array_keys(Income::CATEGORIES))],
            'income_received_on' => ['required', 'date'],
            'income_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'income_payer' => ['nullable', 'string', 'max:150'],
            'income_comment' => ['nullable', 'string', 'max:2000'],
        ], ['income_category.required' => 'Choisissez le type de recette.'], [
            'income_horse_id' => 'cheval', 'income_category' => 'type de recette', 'income_received_on' => 'date',
            'income_amount' => 'montant', 'income_payer' => 'versée par', 'income_comment' => 'commentaire',
        ]);

        return collect($data)->mapWithKeys(fn ($v, $k) => [substr($k, strlen('income_')) => $v])->all();
    }
}
