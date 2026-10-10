<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HorseDocument extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['is_sensitive' => 'boolean'];

    public const CATEGORIES = ['report' => 'Compte rendu', 'prescription' => 'Ordonnance', 'invoice' => 'Facture', 'exam' => 'Résultat d\'examen', 'identification' => 'Identification', 'photo' => 'Photographie', 'other' => 'Autre'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function attachable()
    {
        return $this->morphTo();
    }
}
