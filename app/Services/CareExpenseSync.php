<?php

namespace App\Services;

use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Soin ↔ dépense : un soin et la dépense qui lui est rattachée restent identiques
 * (date, montant, intervenant), quel que soit le côté modifié (formulaire, assistant, facture).
 * Une dépense de soin (vétérinaire, maréchal, ostéopathe, dentiste) crée le soin correspondant.
 */
class CareExpenseSync
{
    /** Catégorie de dépense → catégorie de soin. */
    public const CARE_FOR_EXPENSE = ['veterinary' => 'veterinary', 'farrier' => 'farrier', 'osteopathy' => 'osteopathy', 'dentistry' => 'dentistry'];

    private static bool $syncing = false;

    public function __construct(private HorseAccess $access) {}

    /** Soin modifié : la dépense liée suit (date, montant, intervenant). */
    public function fromCare(CareRecord $care): void
    {
        if (self::$syncing || ! $care->performed_at) {
            return;
        }
        $this->guard(function () use ($care) {
            foreach (Expense::where('care_record_id', $care->id)->get() as $expense) {
                $changes = ['spent_on' => $care->performed_at->toDateString()];
                if ((float) $care->cost > 0) {
                    $changes['amount'] = $care->cost;
                }
                if ($care->professional_id && $care->professional_id !== $expense->professional_id) {
                    $changes['professional_id'] = $care->professional_id;
                    $changes['supplier'] = $care->professional?->fullName() ?? $expense->supplier;
                }
                $expense->fill($changes);
                if ($expense->isDirty()) {
                    $expense->save();
                }
            }
        });
    }

    /** Dépense liée modifiée : le soin suit (date en gardant l'heure, montant, intervenant). */
    public function fromExpense(Expense $expense): void
    {
        if (self::$syncing || ! $expense->care_record_id || ! ($care = CareRecord::find($expense->care_record_id))) {
            return;
        }
        $this->guard(function () use ($expense, $care) {
            $date = $expense->spent_on;
            $care->fill(['cost' => $expense->amount, 'professional_id' => $expense->professional_id ?? $care->professional_id]);
            if ($date && ! $care->performed_at?->isSameDay($date)) {
                $care->performed_at = Carbon::parse($date->toDateString().' '.($care->performed_at?->format('H:i:s') ?? '09:00:00'));
            }
            if ($care->isDirty()) {
                $care->save();
            }
        });
    }

    /**
     * Dépense de soin sans soin : crée le soin dans le carnet de santé (ou rattache un soin du même jour déjà saisi),
     * si l'utilisateur peut modifier la santé du cheval.
     */
    public function careFromExpense(Expense $expense, User $user): ?CareRecord
    {
        $expense->loadMissing(['category', 'horse', 'professional']);
        $key = self::CARE_FOR_EXPENSE[$expense->category?->key] ?? null;
        if ($expense->care_record_id || ! $key || ! $expense->horse || ! $this->access->can($user, $expense->horse, Perm::HEALTH_EDIT)) {
            return null;
        }
        $categoryId = CareCategory::visibleTo($expense->horse->organization_id)->where('key', $key)->value('id');
        if (! $categoryId) {
            return null;
        }
        $care = null;
        $this->guard(function () use ($expense, $user, $categoryId, &$care) {
            // Soin du même jour et de la même catégorie déjà saisi, sans dépense : on le rattache plutôt que de le dupliquer.
            $care = CareRecord::where('horse_id', $expense->horse_id)->where('care_category_id', $categoryId)
                ->whereDate('performed_at', $expense->spent_on)->whereNotIn('id', Expense::whereNotNull('care_record_id')->select('care_record_id'))->first();
            if (! $care) {
                $care = new CareRecord([
                    'care_category_id' => $categoryId,
                    'performed_at' => $expense->spent_on->isToday() ? now() : Carbon::parse($expense->spent_on->toDateString().' 09:00'),
                    'professional_id' => $expense->professional_id,
                    'reason' => Str::limit($expense->comment ?: ($expense->supplier ?: $expense->category->name), 250, ''),
                    'cost' => $expense->amount,
                ]);
                $care->horse_id = $expense->horse_id;
                $care->author_id = $user->id;
            } else {
                $care->fill(['cost' => $care->cost ?: $expense->amount, 'professional_id' => $care->professional_id ?? $expense->professional_id]);
            }
            $care->save();
            $expense->forceFill(['care_record_id' => $care->id])->save();
        });
        return $care;
    }

    private function guard(callable $fn): void
    {
        $was = self::$syncing;
        self::$syncing = true;
        try {
            $fn();
        } finally {
            self::$syncing = $was;
        }
    }
}
