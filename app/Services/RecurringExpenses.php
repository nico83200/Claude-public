<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Génère les échéances des dépenses récurrentes : une dépense « à payer » par échéance arrivée,
 * sans doublon (clé unique récurrence + date), avec rattrapage si le planificateur n'a pas tourné.
 */
class RecurringExpenses
{
    /** Au-delà, l'arriéré est ignoré (récurrence restée inactive très longtemps). */
    private const MAX_CATCH_UP = 36;

    /** À la création, toutes les échéances déjà passées sont créées (10 ans d'échéances hebdomadaires au plus). */
    public const MAX_AT_CREATION = 520;

    public function __construct(private HorseAccess $access) {}

    /** @return int nombre d'échéances créées */
    public function generate(RecurringExpense $rec, ?Carbon $until = null, bool $notify = true, int $max = self::MAX_CATCH_UP): int
    {
        $until = ($until ?? today())->copy()->startOfDay();
        $created = 0;

        DB::transaction(function () use ($rec, $until, $notify, $max, &$created) {
            $rec = RecurringExpense::lockForUpdate()->find($rec->id) ?? $rec;
            for ($i = 0; $i < $max && $rec->isActive() && $rec->next_due_on->lte($until); $i++) {
                $due = $rec->next_due_on->copy();
                $expense = Expense::firstOrNew(['recurring_expense_id' => $rec->id, 'spent_on' => $due->toDateString()]);
                if (! $expense->exists) {
                    $expense->forceFill([
                        'organization_id' => $rec->organization_id, 'author_id' => $rec->author_id, 'horse_id' => $rec->horse_id,
                        'expense_category_id' => $rec->expense_category_id, 'professional_id' => $rec->professional_id,
                        'amount' => $rec->amount, 'supplier' => $rec->supplier, 'comment' => $rec->label, 'paid_on' => null,
                    ])->save();
                    $created++;
                    if ($notify && $due->isSameDay(today())) {
                        $this->notifyDue($rec, $expense);
                    }
                }
                $rec->next_due_on = $rec->dueAfter($due);
            }
            $rec->save();
        });

        return $created;
    }

    private function notifyDue(RecurringExpense $rec, Expense $expense): void
    {
        $user = $rec->author;
        if (! $user || $user->suspended_at) {
            return;
        }
        if ($rec->horse && ! $this->access->can($user, $rec->horse, Perm::EXPENSES_VIEW)) {
            return;
        }
        $what = $rec->label.($rec->horse ? ' – '.$rec->horse->shortName() : '');
        $user->notify(new AppNotification('payment_due', 'Paiement à effectuer',
            $what.' : '.number_format((float) $rec->amount, 2, ',', ' ').' € à régler.', route('budget.index', array_filter(['horse' => $rec->horse_id])), mail: false));
    }
}
