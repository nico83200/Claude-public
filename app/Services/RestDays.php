<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jours de repos d'un cheval, de deux sources : les jours de monte (repos de la semaine type ou posé pour une date)
 * et les événements « Repos » de l'agenda (séries et repos de plusieurs jours compris).
 * Une séance prévue ou notée un jour de repos est signalée, et le propriétaire prévenu quand quelqu'un d'autre l'a prévue.
 */
class RestDays
{
    /** Un repos de l'agenda peut avoir commencé avant la période (repos de plusieurs jours). */
    private const LOOKBACK_DAYS = 62;

    public function __construct(private RidingDays $days, private CalendarService $calendar, private HorseAccess $access) {}

    /** @return array<string, array<int, string[]>> [date => [horse_id => raisons]] */
    public function map(Collection $horses, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $out = [];
        foreach ($this->days->between($horses, $from, $to) as $date => $byHorse) {
            foreach ($byHorse as $horseId => $info) {
                if (! empty($info['rest'])) {
                    $out[$date][$horseId][] = 'repos prévu dans les jours de monte';
                }
            }
        }
        $events = CalendarEvent::whereIn('horse_id', $horses->pluck('id'))->where('type', 'rest')->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNotNull('recurrence')->orWhereBetween('starts_at', [$from->copy()->subDays(self::LOOKBACK_DAYS), $to]))->get();
        foreach ($this->calendar->expand($events, $from->copy()->subDays(self::LOOKBACK_DAYS), $to) as $o) {
            $event = $o['event'];
            [$first, $last] = self::days($o['at'], $event);
            for ($d = $first->max($from); $d->lte($last) && $d->lte($to); $d->addDay()) {
                $out[$d->toDateString()][$event->horse_id][] = '« '.($event->title ?: 'Repos').' » dans l\'agenda';
            }
        }

        return array_map(fn ($byHorse) => array_map(fn ($reasons) => array_values(array_unique($reasons)), $byHorse), $out);
    }

    /**
     * Jours couverts par une occurrence : toute la journée (plusieurs jours si sa durée dépasse 24 h),
     * ou du début à la fin pour un repos à l'heure.
     *
     * @return array{0: Carbon, 1: Carbon} premier et dernier jour (début de journée)
     */
    public static function days(Carbon $at, CalendarEvent $event): array
    {
        $first = $at->copy()->startOfDay();
        $last = $event->all_day
            ? $first->copy()->addDays(max(1, (int) ceil((int) $event->duration_minutes / 1440)) - 1)
            : $at->copy()->addMinutes(max(1, (int) $event->duration_minutes) - 1)->startOfDay();

        return [$first, $last];
    }

    /** Raisons du repos de ce cheval ce jour-là (vide : pas de repos). */
    public function on(Horse $horse, Carbon $date): array
    {
        return $this->map(collect([$horse]), $date, $date)[$date->toDateString()][$horse->id] ?? [];
    }

    /** Formulaire de séance : [horse_id => ['AAAA-MM-JJ' => raisons]] sur les prochaines semaines. */
    public function hintsFor(Collection $horses, int $days = 90): array
    {
        $out = [];
        foreach ($this->map($horses, today(), today()->addDays($days)) as $date => $byHorse) {
            foreach ($byHorse as $horseId => $reasons) {
                $out[$horseId][$date] = implode(', ', $reasons);
            }
        }

        return $out;
    }

    /**
     * Séance un jour de repos : message à afficher (null sinon). Le propriétaire et les gestionnaires du cheval
     * sont prévenus quand la séance est prévue (ou notée) par quelqu'un d'autre.
     */
    public function checkSession(RidingSession $session, User $by): ?string
    {
        $session->loadMissing('horse');
        $horse = $session->horse;
        if (! $horse || ! $session->scheduled_at || $session->status === 'cancelled') {
            return null;
        }
        $reasons = $this->on($horse, $session->scheduled_at);
        if (! $reasons) {
            return null;
        }
        $when = $session->scheduled_at->translatedFormat('l j F');
        $managers = $this->days->candidates($horse)->merge($horse->organization->users)->unique('id')
            ->filter(fn (User $u) => $u->id !== $by->id && $this->access->can($u, $horse, Perm::HORSE_MANAGE));
        $done = $session->status === 'completed';
        foreach ($managers as $manager) {
            $manager->notify(new AppNotification('riding_day', 'Séance un jour de repos : '.$horse->shortName(),
                $by->name.($done ? ' a noté une séance avec ' : ' a prévu une séance avec ').$horse->shortName().' '.$when.' à '.$session->scheduled_at->format('H:i').', jour de repos ('.implode(', ', $reasons).').',
                route('sessions.show', $session)));
        }

        return ucfirst($when).' est un jour de repos pour '.$horse->shortName().' ('.implode(', ', $reasons).') : séance enregistrée quand même'
            .($managers->isNotEmpty() ? ', le propriétaire est prévenu.' : '.');
    }
}
