<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasClientUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'organization_id', 'author_id'];

    protected $casts = ['spent_on' => 'date', 'amount' => 'decimal:2'];

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

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function documents()
    {
        return $this->hasMany(ExpenseDocument::class);
    }
}
