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

    /** Image affichable par les navigateurs (ouverte dans la visionneuse plutôt que téléchargée). */
    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) $this->original_name);
    }
}
