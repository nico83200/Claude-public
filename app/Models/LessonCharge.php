<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Frais de cours d'une personne (cours, coaching, déplacement) : visibles par elle seule (et par qui les a notés) ;
 * recopiés dans le budget du cheval (expense_id) seulement quand elle en est le propriétaire.
 */
class LessonCharge extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'charged_on' => 'date', 'paid_on' => 'date', 'removed_with_source' => 'boolean'];

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function session()
    {
        return $this->belongsTo(RidingSession::class, 'riding_session_id')->withTrashed();
    }

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id')->withTrashed();
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class);
    }

    public function package()
    {
        return $this->belongsTo(LessonPackage::class)->withTrashed();
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    /** Fiche d'origine : la séance ou le concours. */
    public function sourceUrl(): ?string
    {
        return match (true) {
            $this->riding_session_id !== null => route('sessions.show', $this->riding_session_id),
            $this->calendar_event_id !== null => route('calendar.show', $this->calendar_event_id),
            default => null,
        };
    }
}
