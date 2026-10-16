<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Recette du budget (demi-pension, gains de concours, vente…), rattachée à un cheval. */
class Income extends Model
{
    use HasClientUuid, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'organization_id', 'author_id'];

    protected $casts = ['received_on' => 'date', 'collected_on' => 'date', 'amount' => 'decimal:2'];

    public const CATEGORIES = [
        'half_lease' => 'Demi-pension',
        'prize' => 'Gains de concours',
        'boarding' => 'Pension perçue',
        'lease' => 'Location ou prêt du cheval',
        'lessons' => 'Cours donnés',
        'sale' => 'Vente (cheval, matériel)',
        'sponsorship' => 'Sponsoring',
        'refund' => 'Remboursement',
        'other' => 'Autre recette',
    ];

    /** Reçue (comme « payée » pour une dépense) ; sinon « à recevoir ». */
    public function isReceived(): bool
    {
        return $this->collected_on !== null;
    }

    /** À recevoir et date dépassée. */
    public function isOverdue(): bool
    {
        return ! $this->isReceived() && $this->received_on->lt(today());
    }

    public function recurring()
    {
        return $this->belongsTo(RecurringIncome::class, 'recurring_income_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Recette';
    }

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
