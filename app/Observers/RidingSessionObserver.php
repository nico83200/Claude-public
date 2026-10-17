<?php

namespace App\Observers;

use App\Models\Horse;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\LessonCosts;

/**
 * Chaque séance (prévue, en cours ou terminée) a son événement dans le calendrier,
 * tenu à jour quelle que soit l'origine de la modification (web, mode séance, assistant).
 */
class RidingSessionObserver
{
    public function __construct(private CalendarService $calendar, private LessonCosts $costs) {}

    /** Enseignant saisi : relié à sa fiche d'intervenant (ses tarifs) quand le nom correspond à un enseignant de l'utilisateur. */
    public function saving(RidingSession $session): void
    {
        if (! $session->isDirty('instructor') && ($session->exists || $session->professional_id)) {
            return;
        }
        $name = mb_strtolower(trim((string) $session->instructor));
        $user = auth()->user() ?? ($session->created_by ? User::find($session->created_by) : null);
        $session->professional_id = $name !== '' && $user
            ? Professional::whereIn('organization_id', $user->memberships()->pluck('organization_id'))->where('kind', 'instructor')->whereNull('archived_at')
                ->get()->first(fn (Professional $p) => mb_strtolower($p->fullName()) === $name)?->id
            : null;
    }

    public function saved(RidingSession $session): void
    {
        $this->costs->fromSession($session);
        $horse = $session->horse;
        if (! $horse || ! $session->scheduled_at) {
            return;
        }
        $this->calendar->syncFor($session, $horse, 'session', $session->title().' – '.$horse->shortName(), $session->scheduled_at, [
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
        $this->costs->sessionDeleted($session);
    }

    public function restored(RidingSession $session): void
    {
        $this->costs->sessionRestored($session);
    }
}
