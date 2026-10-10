<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseDocument extends Model
{
    protected $guarded = ['id'];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
