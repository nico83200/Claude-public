<?php

namespace App\Http\Controllers;

use App\Models\RecurringExpense;
use App\Services\Audit;
use App\Services\RecurringExpenses;
use App\Support\Perm;
use Illuminate\Http\Request;

/** Dépenses récurrentes : modification, suspension, arrêt. Les échéances déjà créées restent dans le budget. */
class RecurringExpenseController extends Controller
{
    public function update(Request $request, RecurringExpense $recurring, RecurringExpenses $service)
    {
        $this->authorizeManage($request, $recurring);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'frequency' => ['required', 'in:'.implode(',', array_keys(RecurringExpense::FREQUENCIES))],
            'next_due_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:next_due_on'],
        ]);
        $restart = $data['frequency'] !== $recurring->frequency || $data['next_due_on'] !== $recurring->next_due_on->toDateString();
        $recurring->fill($data);
        if ($restart) {
            // Nouveau rythme ou nouvelle date : les échéances suivantes se calculent depuis la prochaine échéance.
            $recurring->starts_on = $data['next_due_on'];
        }
        $recurring->save();
        $service->generate($recurring, notify: false);

        return back()->with('success', 'Dépense récurrente mise à jour. Les échéances passées ne changent pas.');
    }

    public function toggle(Request $request, RecurringExpense $recurring, RecurringExpenses $service)
    {
        $this->authorizeManage($request, $recurring);
        if ($recurring->paused_at) {
            // Reprise : pas d'arriéré pour la période suspendue.
            $recurring->paused_at = null;
            if ($recurring->next_due_on->lt(today())) {
                $recurring->next_due_on = $recurring->dueAfter(today()->subDay());
            }
            $recurring->save();
            $service->generate($recurring, notify: false);

            return back()->with('success', 'Dépense récurrente reprise.');
        }
        $recurring->update(['paused_at' => now()]);

        return back()->with('success', 'Dépense récurrente suspendue : plus aucune échéance ne sera créée.');
    }

    public function destroy(Request $request, RecurringExpense $recurring)
    {
        $this->authorizeManage($request, $recurring);
        $recurring->delete();
        Audit::log('expense.recurring_deleted', $recurring, ['label' => $recurring->label], $recurring->organization_id);

        return back()->with('success', 'Dépense récurrente arrêtée. Les échéances déjà enregistrées sont conservées.');
    }

    private function authorizeManage(Request $request, RecurringExpense $rec): void
    {
        $user = $request->user();
        $ok = $rec->horse
            ? $this->horseCan($rec->horse, Perm::EXPENSES_CREATE) && ($rec->author_id === $user->id || $this->horseCan($rec->horse, Perm::HORSE_MANAGE))
            : $user->canInOrg($rec->organization_id, Perm::EXPENSES_ORG_VIEW);
        abort_unless($ok, 403, 'Vous ne pouvez pas modifier cette dépense récurrente.');
    }
}
