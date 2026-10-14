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
        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) ($this->original_name ?: $this->path));
    }

    public function isPdf(): bool
    {
        return (bool) preg_match('/\.pdf$/i', (string) ($this->original_name ?: $this->path));
    }

    /** Type d'aperçu dans l'application : « image », « pdf », ou null (fichier seulement téléchargeable). */
    public function viewerType(): ?string
    {
        return $this->isImage() ? 'image' : ($this->isPdf() ? 'pdf' : null);
    }
}
