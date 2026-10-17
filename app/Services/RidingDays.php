<?php

namespace App\Services;

use App\Models\HalfLease;
use App\Models\Horse;
use App\Models\RidingDay;
use App\Models\RidingDayChange;
use App\Models\RidingDayRequest;
use App\Models\RidingSession;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Support\Fr;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jours de monte d'un cheval partagé : chaque jour de la semaine revient à une personne (propriétaire, demi-pension…),
 * avec des échanges ponctuels (« je te laisse samedi ») ou un jour laissé libre. Seules comptent les personnes qui
 * peuvent encore monter le cheval (droit de créer des séances) : un partage terminé libère ses jours.
 */
class RidingDays
{
    private array $candidates = [];

    private array $mayRide = [];

    public function __construct(private HorseAccess $access) {}

    /** Personnes qui peuvent monter le cheval (créer des séances), par ordre alphabétique. */
    public function candidates(Horse $horse): Collection
    {
        return $this->candidates[$horse->id] ??= User::whereIn('id', $horse->organization->users()->pluck('users.id')
            ->merge($horse->accessGrants()->current()->pluck('user_id'))
            ->merge($horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))->unique()->values())
            ->orderBy('name')->get()->filter(fn (User $u) => $this->mayRide($u, $horse))->values();
    }

    public function mayRide(User $user, Horse $horse): bool
    {
        return $this->mayRide[$user->id.':'.$horse->id] ??= $this->access->can($user, $horse, Perm::SESSIONS_CREATE);
    }

    /** Semaine type : jour (1 = lundi) => personne, sans les personnes qui ne peuvent plus monter. */
    public function weekly(Horse $horse): array
    {
        return RidingDay::with('user')->where('horse_id', $horse->id)->orderBy('weekday')->get()
            ->filter(fn (RidingDay $d) => $d->user && $this->mayRide($d->user, $horse))
            ->mapWithKeys(fn (RidingDay $d) => [$d->weekday => $d->user])->all();
    }

    public function hasAny(Horse $horse): bool
    {
        return RidingDay::where('horse_id', $horse->id)->exists() || RidingDayChange::where('horse_id', $horse->id)->where('date', '>=', today())->exists();
    }

    /** Qui monte ce jour-là : ['user' => ?User, 'changed' => échange ponctuel, 'change' => ?RidingDayChange]. */
    public function on(Horse $horse, Carbon|string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();

        return $this->between(collect([$horse]), $day, $day)[$day->toDateString()][$horse->id] ?? ['user' => null, 'changed' => false, 'change' => null];
    }

    /**
     * Agenda, accueil : [date => [horse_id => ['user' => ?User, 'changed' => bool, 'change' => ?RidingDayChange]]],
     * seulement pour les jours attribués ou échangés.
     */
    public function between(Collection $horses, Carbon $from, Carbon $to): array
    {
        $ids = $horses->pluck('id')->all();
        if (! $ids) {
            return [];
        }
        $weekly = RidingDay::with('user')->whereIn('horse_id', $ids)->get()->groupBy('horse_id');
        $changes = RidingDayChange::with(['user', 'fromUser'])->whereIn('horse_id', $ids)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()
            ->keyBy(fn ($c) => $c->horse_id.':'.$c->date->toDateString());
        if ($weekly->isEmpty() && $changes->isEmpty()) {
            return [];
        }
        $byId = $horses->keyBy('id');
        $out = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            foreach ($ids as $id) {
                $change = $changes->get($id.':'.$d->toDateString());
                $user = $change ? $change->user : $weekly->get($id)?->firstWhere('weekday', $d->isoWeekday())?->user;
                if ($user && ! $this->mayRide($user, $byId[$id])) {
                    $user = null;
                }
                if ($user || $change) {
                    $out[$d->toDateString()][$id] = ['user' => $user, 'changed' => (bool) $change, 'change' => $change];
                }
            }
        }

        return $out;
    }

    /** Semaine type modifiée par le propriétaire ou le gérant ; les personnes dont les jours changent sont prévenues. */
    public function setWeekly(Horse $horse, array $assignments, User $by): void
    {
        $allowed = $this->candidates($horse)->keyBy('id');
        $before = $this->weekly($horse);
        DB::transaction(function () use ($horse, $assignments, $allowed) {
            foreach (array_keys(RidingDay::WEEKDAYS) as $weekday) {
                $userId = (int) ($assignments[$weekday] ?? 0);
                if ($userId && ! $allowed->has($userId)) {
                    throw ValidationException::withMessages(['days.'.$weekday => 'Cette personne ne peut pas monter ce cheval.']);
                }
                $userId
                    ? RidingDay::updateOrCreate(['horse_id' => $horse->id, 'weekday' => $weekday], ['user_id' => $userId])
                    : RidingDay::where('horse_id', $horse->id)->where('weekday', $weekday)->delete();
            }
        });
        $after = $this->weekly($horse);
        app(HalfLeases::class)->syncFromWeek($horse);
        $daysOf = fn (array $map, int $id) => collect($map)->filter(fn ($u) => $u->id === $id)->keys()->sort()->values()->all();
        $people = collect($before)->merge($after)->unique('id')->reject(fn ($u) => $u->id === $by->id);
        foreach ($people as $person) {
            $days = $daysOf($after, $person->id);
            if ($days === $daysOf($before, $person->id)) {
                continue;
            }
            $person->notify(new AppNotification('riding_day', 'Jours de monte de '.$horse->shortName(),
                $days ? 'Vos jours de monte avec '.$horse->shortName().' : '.self::list($days).'.' : 'Vous n\'avez plus de jour de monte habituel avec '.$horse->shortName().'.',
                route('horses.riding-days', $horse)));
        }
    }

    /**
     * Échange ponctuel : la personne du jour (ou le propriétaire, le gérant) laisse ce jour à quelqu'un d'autre, ou le laisse libre.
     * Prévient la personne qui reçoit le jour ; un jour libéré par une demi-pension est signalé au propriétaire.
     */
    public function give(Horse $horse, Carbon $date, ?User $to, User $by): RidingDayChange
    {
        $day = $date->copy()->startOfDay();
        if ($day->lt(today())) {
            throw ValidationException::withMessages(['date' => 'Ce jour est passé.']);
        }
        $current = $this->on($horse, $day)['user'];
        $manager = $this->access->can($by, $horse, Perm::HORSE_MANAGE);
        if (! $manager && $current?->id !== $by->id) {
            throw ValidationException::withMessages(['date' => 'Seule la personne qui monte ce jour-là (ou le propriétaire) peut le laisser.']);
        }
        if ($to && ! $this->candidates($horse)->contains('id', $to->id)) {
            throw ValidationException::withMessages(['to' => 'Cette personne ne peut pas monter ce cheval.']);
        }
        $usual = $this->weekly($horse)[$day->isoWeekday()] ?? null;
        // Retour au jour habituel : l'échange disparaît.
        if ($to?->id === $usual?->id) {
            RidingDayChange::where('horse_id', $horse->id)->whereDate('date', $day)->delete();
            $change = new RidingDayChange(['horse_id' => $horse->id, 'date' => $day, 'user_id' => $to?->id, 'from_user_id' => $current?->id, 'created_by' => $by->id]);
        } else {
            $change = RidingDayChange::updateOrCreate(['horse_id' => $horse->id, 'date' => $day->toDateString()],
                ['user_id' => $to?->id, 'from_user_id' => $current?->id, 'created_by' => $by->id]);
        }
        $when = $day->translatedFormat('l j F');
        if ($to && $to->id !== $by->id) {
            $to->notify(new AppNotification('riding_day', 'Jour de monte : '.$when,
                $by->name.' vous laisse '.$when.' avec '.$horse->shortName().'.', route('horses.riding-days', $horse)));
        }
        if ($current && $current->id !== $by->id && $current->id !== $to?->id) {
            $current->notify(new AppNotification('riding_day', 'Jour de monte : '.$when,
                $by->name.' a attribué '.$when.' '.($to ? 'à '.$to->name : 'comme jour libre').' pour '.$horse->shortName().'.', route('horses.riding-days', $horse)));
        }
        if (! $to && ! $manager) {
            foreach ($this->candidates($horse)->filter(fn ($u) => $u->id !== $by->id && $this->access->can($u, $horse, Perm::HORSE_MANAGE)) as $owner) {
                $owner->notify(new AppNotification('riding_day', 'Jour de monte libéré : '.$when,
                    $by->name.' laisse '.$when.' libre pour '.$horse->shortName().'.', route('horses.riding-days', $horse)));
            }
        }

        return $change;
    }

    /**
     * « Puis-je monter samedi ? » : demande à la personne du jour (au propriétaire pour un jour libre),
     * éventuellement contre un de ses propres jours (échange).
     */
    public function request(Horse $horse, Carbon $date, User $by, ?Carbon $swap = null): RidingDayRequest
    {
        $day = $date->copy()->startOfDay();
        if ($day->lt(today())) {
            throw ValidationException::withMessages(['date' => 'Ce jour est passé.']);
        }
        if (! $this->mayRide($by, $horse)) {
            throw ValidationException::withMessages(['date' => 'Vous ne pouvez pas monter ce cheval.']);
        }
        $holder = $this->on($horse, $day)['user'];
        if ($holder?->id === $by->id) {
            throw ValidationException::withMessages(['date' => 'C\'est déjà votre jour.']);
        }
        if ($swap) {
            $swap = $swap->copy()->startOfDay();
            if (! $holder || $swap->lt(today()) || $swap->isSameDay($day) || $this->on($horse, $swap)['user']?->id !== $by->id) {
                throw ValidationException::withMessages(['swap_date' => 'L\'échange se fait contre un de vos jours à venir, avec la personne du jour.']);
            }
        }
        if (RidingDayRequest::pending()->where('horse_id', $horse->id)->where('requester_id', $by->id)->whereDate('date', $day)->exists()) {
            throw ValidationException::withMessages(['date' => 'Vous avez déjà demandé ce jour.']);
        }
        $request = RidingDayRequest::create(['horse_id' => $horse->id, 'requester_id' => $by->id, 'holder_id' => $holder?->id,
            'date' => $day->toDateString(), 'swap_date' => $swap?->toDateString()]);
        $when = $day->translatedFormat('l j F');
        $text = $by->name.' aimerait monter '.$horse->shortName().' '.$when.($swap ? ', en échange de son '.$swap->translatedFormat('l j F') : '').'.';
        $recipients = $holder ? collect([$holder]) : $this->candidates($horse)->filter(fn ($u) => $u->id !== $by->id && $this->access->can($u, $horse, Perm::HORSE_MANAGE));
        foreach ($recipients as $person) {
            $person->notify(new AppNotification('riding_day', 'Demande de jour : '.$when, $text, route('horses.riding-days', $horse)));
        }

        return $request;
    }

    /** Réponse de la personne du jour (ou du propriétaire) : le jour, et l'échange s'il y en a un, sont appliqués à l'accord. */
    public function answer(RidingDayRequest $request, bool $accept, User $by): void
    {
        $horse = $request->horse;
        if ($request->status !== 'pending' || $request->date->lt(today())) {
            throw ValidationException::withMessages(['request' => 'Cette demande n\'est plus en attente.']);
        }
        $holder = $this->on($horse, $request->date)['user'];
        if ($holder?->id !== $by->id && ! $this->access->can($by, $horse, Perm::HORSE_MANAGE)) {
            throw ValidationException::withMessages(['request' => 'Seule la personne du jour (ou le propriétaire) peut répondre.']);
        }
        $requester = $request->requester;
        DB::transaction(function () use ($request, $accept, $by, $horse, $holder, $requester) {
            $request->update(['status' => $accept ? 'accepted' : 'declined', 'answered_by' => $by->id, 'answered_at' => now()]);
            if (! $accept) {
                return;
            }
            $this->give($horse, $request->date, $requester, $by);
            // Échange : le jour proposé en retour revient à la personne qui a laissé le sien.
            if ($request->swap_date && $holder && $request->swap_date->gte(today()) && $this->on($horse, $request->swap_date)['user']?->id === $requester->id) {
                $this->give($horse, $request->swap_date, $holder, $requester);
            }
        });
        if (! $accept) {
            $requester->notify(new AppNotification('riding_day', 'Jour de monte : '.$request->date->translatedFormat('l j F'),
                $by->name.' ne peut pas vous laisser '.$request->date->translatedFormat('l j F').' avec '.$horse->shortName().'.', route('horses.riding-days', $horse)));
        }
    }

    /**
     * Absence : tous ses jours de la période libérés (ou laissés à quelqu'un) en une fois, une seule notification.
     *
     * @return int nombre de jours concernés
     */
    public function absence(Horse $horse, User $by, Carbon $from, Carbon $to, ?User $giveTo = null): int
    {
        $from = $from->copy()->startOfDay()->max(today());
        $to = $to->copy()->startOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 92) {
            throw ValidationException::withMessages(['to' => 'Période de 3 mois au plus, à venir.']);
        }
        if ($giveTo && (! $this->candidates($horse)->contains('id', $giveTo->id) || $giveTo->id === $by->id)) {
            throw ValidationException::withMessages(['give_to' => 'Cette personne ne peut pas monter ce cheval.']);
        }
        $weekly = $this->weekly($horse);
        $days = collect($this->between(collect([$horse]), $from, $to))->filter(fn ($byHorse) => ($byHorse[$horse->id]['user'] ?? null)?->id === $by->id)->keys();
        DB::transaction(function () use ($days, $horse, $by, $giveTo, $weekly) {
            foreach ($days as $date) {
                $usual = $weekly[Carbon::parse($date)->isoWeekday()] ?? null;
                if ($giveTo && $usual?->id === $giveTo->id) {
                    RidingDayChange::where('horse_id', $horse->id)->whereDate('date', $date)->delete();
                } else {
                    RidingDayChange::updateOrCreate(['horse_id' => $horse->id, 'date' => $date], ['user_id' => $giveTo?->id, 'from_user_id' => $by->id, 'created_by' => $by->id]);
                }
            }
        });
        if ($days->isNotEmpty()) {
            $list = $days->map(fn ($d) => Carbon::parse($d)->translatedFormat('l j'))->implode(', ');
            $period = 'du '.$from->translatedFormat('j F').' au '.$to->translatedFormat('j F');
            $recipients = $giveTo ? collect([$giveTo]) : $this->candidates($horse)->filter(fn ($u) => $u->id !== $by->id && $this->access->can($u, $horse, Perm::HORSE_MANAGE));
            foreach ($recipients as $person) {
                $person->notify(new AppNotification('riding_day', 'Absence : jours de monte de '.$horse->shortName(), $giveTo
                    ? $by->name.' vous laisse ses jours avec '.$horse->shortName().' '.$period.' : '.$list.'.'
                    : $by->name.' ne monte pas '.$period.' : '.Fr::count($days->count(), 'jour libéré', 'jours libérés').' ('.$list.').', route('horses.riding-days', $horse)));
            }
        }

        return $days->count();
    }

    /** Mois en cours (ou donné) : jours prévus et séances notées, par personne. */
    public function monthCount(Horse $horse, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        // Demi-pension : seulement les jours entre son début et sa fin.
        $leases = HalfLease::where('horse_id', $horse->id)->whereIn('status', ['active', 'ended'])->whereNotNull('user_id')->get()->keyBy('user_id');
        $planned = collect($this->between(collect([$horse]), $start, $end))
            ->map(fn ($byHorse, $date) => ($u = $byHorse[$horse->id]['user'] ?? null) && (! ($l = $leases->get($u->id)) || ($date >= $l->starts_on->toDateString() && (! $l->ends_on || $date <= $l->ends_on->toDateString()))) ? $u : null)
            ->filter()->countBy(fn ($u) => $u->id);
        $sessions = RidingSession::where('horse_id', $horse->id)->where('status', 'completed')->whereBetween('scheduled_at', [$start, $end])->get(['rider_id', 'created_by'])
            ->countBy(fn ($s) => $s->rider_id ?: $s->created_by);
        $people = $this->candidates($horse)->keyBy('id');

        return collect($planned->keys())->merge($sessions->keys())->unique()->filter(fn ($id) => $people->has($id))
            ->mapWithKeys(fn ($id) => [$id => ['user' => $people[$id], 'planned' => (int) ($planned[$id] ?? 0), 'sessions' => (int) ($sessions[$id] ?? 0)]])
            ->sortByDesc('planned')->all();
    }

    /** Séance prévue le jour de quelqu'un d'autre : la personne du jour (ou null). */
    public function conflict(RidingSession $session): ?User
    {
        $session->loadMissing('horse');
        if (! $session->horse || ! $session->scheduled_at || $session->status === 'cancelled') {
            return null;
        }
        $owner = $this->on($session->horse, $session->scheduled_at)['user'];
        $rider = $session->rider_id ?: $session->created_by;

        return $owner && $owner->id !== $rider ? $owner : null;
    }

    /** Prévient la personne du jour qu'une séance est prévue sur son jour. */
    public function notifyConflict(RidingSession $session, User $by): ?User
    {
        $owner = $this->conflict($session);
        if ($owner && $owner->id !== $by->id) {
            $owner->notify(new AppNotification('riding_day', 'Séance prévue sur votre jour',
                $by->name.' a prévu une séance avec '.$session->horse->shortName().' '.$session->scheduled_at->translatedFormat('l j F').' à '.$session->scheduled_at->format('H:i').', votre jour de monte.',
                route('sessions.show', $session)));
        }

        return $owner;
    }

    /** « lundi, mercredi et samedi » */
    public static function list(array $weekdays): string
    {
        $names = collect($weekdays)->map(fn ($d) => mb_strtolower(RidingDay::WEEKDAYS[$d]))->values();

        return $names->count() > 1 ? $names->slice(0, -1)->implode(', ').' et '.$names->last() : (string) $names->first();
    }

    /** Données pour l'aide du formulaire de séance (prochaines semaines) : [horse_id => ['dates' => [date => [id, nom]]]]. */
    public function hintsFor(Collection $horses, int $days = 90): array
    {
        $map = $this->between($horses, today(), today()->addDays($days));
        $out = [];
        foreach ($map as $date => $byHorse) {
            foreach ($byHorse as $horseId => $info) {
                $out[$horseId][$date] = $info['user'] ? [$info['user']->id, $info['user']->name] : null;
            }
        }

        return $out;
    }
}
