<?php

namespace App\Models;

use App\Models\Concerns\Recurs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Dépense qui revient (pension, assurance…) : chaque échéance devient une dépense « à payer ». */
class RecurringExpense extends Model
{
    use Recurs, SoftDeletes;

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
}
