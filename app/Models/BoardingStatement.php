<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Relevé mensuel de pension adressé à un propriétaire. Les lignes sont figées à la création. */
class BoardingStatement extends Model
{
    public const STATUSES = ['draft' => 'Brouillon', 'sent' => 'Envoyé', 'paid' => 'Payé'];

    protected $guarded = ['id', 'organization_id'];

    protected $casts = ['lines' => 'array', 'sent_at' => 'datetime', 'paid_on' => 'date', 'total' => 'decimal:2'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function periodLabel(): string
    {
        return ucfirst(Carbon::createFromFormat('Y-m-d', $this->period.'-01')->translatedFormat('F Y'));
    }
}
