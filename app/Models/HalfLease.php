<?php

namespace App\Models;

use App\Services\RidingDays;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Demi-pension d'une personne sur un cheval : proposée par le propriétaire, active une fois acceptée
 * (accès « Demi-pension », jours habituels à partir du début), terminée à sa date de fin.
 */
class HalfLease extends Model
{
    public const STATUSES = ['proposed' => 'En attente de réponse', 'active' => 'En cours', 'declined' => 'Refusée', 'cancelled' => 'Annulée', 'ended' => 'Terminée'];

    public const NOTICES = [15 => '15 jours', 30 => '1 mois', 60 => '2 mois'];

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date', 'trial_ends_on' => 'date', 'ends_on' => 'date', 'weekdays' => 'array', 'permissions' => 'array',
        'monthly_amount' => 'decimal:2', 'skipped_months' => 'array', 'accepted_at' => 'datetime', 'answered_at' => 'datetime', 'days_applied_at' => 'datetime', 'trial_reminded_at' => 'datetime',
    ];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }

    public function grant()
    {
        return $this->belongsTo(HorseAccessGrant::class, 'grant_id');
    }

    /** Proposée ou en cours (pas refusée, annulée ni terminée). */
    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['proposed', 'active']);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['proposed', 'active'], true);
    }

    /** En cours et pas encore passée sa date de fin (la tâche quotidienne la clôt le lendemain). */
    public function isRunning(): bool
    {
        return $this->status === 'active' && (! $this->ends_on || $this->ends_on->gte(today()));
    }

    public function riderName(): string
    {
        return $this->user?->name ?? $this->name;
    }

    public function statusLabel(): string
    {
        if ($this->status === 'active' && $this->starts_on->isFuture()) {
            return 'Commence le '.$this->starts_on->translatedFormat('j F');
        }
        if ($this->status === 'active' && $this->ends_on && ! $this->ends_on->isPast()) {
            return 'Jusqu\'au '.$this->ends_on->translatedFormat('j F Y');
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return ['proposed' => 'amber', 'active' => 'green', 'ended' => 'slate'][$this->status] ?? 'red';
    }

    /** « mardi, jeudi et samedi » */
    public function daysLabel(): string
    {
        return RidingDays::list(array_map('intval', $this->weekdays ?? []));
    }

    /** « 180,00 € par mois, payable le 5 » ; « Sans contrepartie financière » pour une demi-pension gratuite. */
    public function priceLabel(): ?string
    {
        if ($this->monthly_amount === null) {
            return null;
        }
        if ((float) $this->monthly_amount === 0.0) {
            return 'Sans contrepartie financière';
        }

        return number_format((float) $this->monthly_amount, 2, ',', ' ').' € par mois'.($this->payment_day ? ', payable le '.$this->payment_day : '');
    }

    /** Date de fin proposée : aujourd'hui + le préavis. */
    public function noticeDate(): Carbon
    {
        $date = today()->addDays((int) $this->notice_days);

        return $this->ends_on && $this->ends_on->lt($date) ? $this->ends_on->copy() : $date;
    }

    /** La personne (compte de l'adresse invitée, ou compte relié) : voit la fiche, accepte ou refuse, met fin. */
    public function isRider(User $user): bool
    {
        return $this->user_id === $user->id || (strtolower($this->email) === strtolower($user->email) && $user->hasVerifiedEmail());
    }
}
