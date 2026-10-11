<?php

namespace App\Observers;

use App\Models\Horse;
use App\Models\RidingSession;
use App\Services\CalendarService;

/**
 * Chaque séance (prévue, en cours ou terminée) a son événement dans le calendrier,
 * tenu à jour quelle que soit l'origine de la modification (web, mode live, synchro hors ligne).
 */
class RidingSessionObserver
{
    public function __construct(private CalendarService $calendar) {}

    public function saved(RidingSession $session): void
    {
        $horse = $session->horse;
        if (! $horse || ! $session->scheduled_at) {
            return;
        }
        $type = RidingSession::TYPES[$session->session_type] ?? 'Séance';
        $this->calendar->syncFor($session, $horse, 'session', 'Séance '.mb_strtolower($type).' – '.$horse->shortName(), $session->scheduled_at, [
            'status' => match ($session->status) {
                'completed' => 'done',
                'cancelled' => 'cancelled',
                default => 'planned',
            },
            'duration_minutes' => max(5, (int) ($session->status === 'completed' ? ($session->actual_minutes ?: $session->planned_minutes) : $session->planned_minutes) ?: 60),
            'notes' => $session->objective,
            'responsible_user_id' => $session->rider_id,
        ]);
    }

    public function deleted(RidingSession $session): void
    {
        $this->calendar->syncFor($session, $session->horse ?? new Horse, 'session', '', null);
    }
}
