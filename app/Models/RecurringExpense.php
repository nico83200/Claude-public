<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** Dépense qui revient (pension, assurance…) : chaque échéance devient une dépense « à payer ». */
class RecurringExpense extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'organization_id', 'author_id'];

    protected $casts = ['amount' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'next_due_on' => 'date', 'paused_at' => 'datetime'];

    public const FREQUENCIES = ['weekly' => 'Chaque semaine', 'monthly' => 'Chaque mois', 'quarterly' => 'Chaque trimestre', 'yearly' => 'Chaque année'];

    public const SHORT = ['weekly' => '/ semaine', 'monthly' => '/ mois', 'quarterly' => '/ trimestre', 'yearly' => '/ an'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function occurrences()
    {
        return $this->hasMany(Expense::class);
    }

    public function isActive(): bool
    {
        return ! $this->paused_at && (! $this->ends_on || $this->next_due_on->lte($this->ends_on));
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
