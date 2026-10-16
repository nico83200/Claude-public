<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Échéancier commun aux dépenses et aux recettes récurrentes :
 * starts_on, next_due_on, ends_on, paused_at et frequency (weekly, monthly, quarterly, yearly).
 */
trait Recurs
{
    public function isActive(): bool
    {
        return ! $this->paused_at && (! $this->ends_on || $this->next_due_on->lte($this->ends_on));
    }

    /** Montant ramené au mois (hebdomadaire × 52/12, trimestriel ÷ 3, annuel ÷ 12). */
    public function monthlyAmount(): float
    {
        return (float) $this->amount * match ($this->frequency) {
            'weekly' => 52 / 12, 'quarterly' => 1 / 3, 'yearly' => 1 / 12, default => 1,
        };
    }

    /** Terminée : date de fin dépassée (une récurrence suspendue reste en cours). */
    public function isEnded(): bool
    {
        return ! $this->paused_at && ! $this->isActive();
    }

    /** N-ième échéance (0 = première), calculée depuis la date de départ pour ne jamais dériver (31 → 28/29/30 → 31). */
    public function occurrence(int $n): Carbon
    {
        $start = $this->starts_on->copy();

        return match ($this->frequency) {
            'weekly' => $start->addWeeks($n),
            'quarterly' => $start->addMonthsNoOverflow(3 * $n),
            'yearly' => $start->addYearsNoOverflow($n),
            default => $start->addMonthsNoOverflow($n),
        };
    }

    /** Première échéance strictement postérieure à $date. */
    public function dueAfter(Carbon $date): Carbon
    {
        for ($n = 0; $n < 5000; $n++) {
            $d = $this->occurrence($n);
            if ($d->gt($date)) {
                return $d;
            }
        }

        return $date->copy()->addYear();
    }
}
