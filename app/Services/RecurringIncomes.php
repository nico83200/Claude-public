<?php

namespace App\Services;

use App\Models\Income;
use App\Models\RecurringIncome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Échéances des recettes récurrentes : une recette « à recevoir » par échéance arrivée,
 * sans doublon (clé unique récurrence + date), avec rattrapage si le planificateur n'a pas tourné.
 */
class RecurringIncomes
{
    /** Au-delà, l'arriéré est ignoré (récurrence restée inactive très longtemps). */
    public const MAX_CATCH_UP = 36;

    /** À la création, toutes les échéances déjà passées sont créées (10 ans d'échéances hebdomadaires au plus). */
    public const MAX_AT_CREATION = 520;

    /** @return int nombre d'échéances créées */
    public function generate(RecurringIncome $rec, ?Carbon $until = null, int $max = self::MAX_CATCH_UP): int
    {
        $until = ($until ?? today())->copy()->startOfDay();
        $created = 0;

        DB::transaction(function () use ($rec, $until, $max, &$created) {
            $rec = RecurringIncome::lockForUpdate()->find($rec->id) ?? $rec;
            for ($i = 0; $i < $max && $rec->isActive() && $rec->next_due_on->lte($until); $i++) {
                $due = $rec->next_due_on->copy();
                $income = Income::firstOrNew(['recurring_income_id' => $rec->id, 'received_on' => $due->toDateString()]);
                if (! $income->exists) {
                    $income->forceFill([
                        'organization_id' => $rec->organization_id, 'author_id' => $rec->author_id, 'horse_id' => $rec->horse_id,
                        'category' => $rec->category, 'amount' => $rec->amount, 'payer' => $rec->payer,
                        'comment' => $rec->comment ?: $rec->label, 'collected_on' => null,
                    ])->save();
                    $created++;
                }
                $rec->next_due_on = $rec->dueAfter($due);
            }
            $rec->save();
        });

        return $created;
    }
}
