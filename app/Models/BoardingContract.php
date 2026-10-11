<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pension d'un cheval dans une écurie : montant mensuel facturé à son propriétaire. */
class BoardingContract extends Model
{
    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'monthly_amount' => 'decimal:2'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Clé regroupant les relevés : compte du propriétaire, sinon nom et email. */
    public function ownerKey(): string
    {
        return $this->owner_user_id ? 'u'.$this->owner_user_id : 'n'.md5(mb_strtolower(trim($this->owner_name).'|'.trim((string) $this->owner_email)));
    }
}
