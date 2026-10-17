<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HorseDocument extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['is_sensitive' => 'boolean', 'valid_until' => 'date', 'expiry_warned_at' => 'datetime', 'expired_notified_at' => 'datetime'];

    /** Documents d'une demi-pension (rattachés à sa fiche). */
    public const HALF_LEASE_CATEGORIES = ['contract' => 'Contrat signé', 'insurance' => 'Attestation d\'assurance', 'license' => 'Licence', 'rules' => 'Règlement de l\'écurie', 'parental' => 'Autorisation parentale', 'other' => 'Autre'];

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

    /** Image affichable par les navigateurs (ouverte dans la visionneuse plutôt que téléchargée). */
    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) $this->original_name);
    }

    public function isPdf(): bool
    {
        return (bool) preg_match('/\.pdf$/i', (string) $this->original_name);
    }

    /** Type d'aperçu plein écran dans l'application : « image », « pdf », ou null (fichier seulement téléchargeable). */
    public function viewerType(): ?string
    {
        return $this->isImage() ? 'image' : ($this->isPdf() ? 'pdf' : null);
    }
}
