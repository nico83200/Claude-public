<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\Treatment;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * « À valider » : rendez-vous et séances passés (fin dépassée) que personne n'a encore marqués réalisés ou annulés,
 * et que l'utilisateur a le droit de valider. Un rendez-vous de soin validé rejoint le carnet de santé
 * (et clôt le contrôle prévu) : tenir cette liste à jour garde les prochaines échéances justes.
 */
class ToValidate
{
    /** Rendez-vous et séances des 30 derniers jours (au-delà, l'agenda reste la référence). */
    public const DAYS = 30;

    /** Séries récurrentes : seulement les occurrences de la dernière semaine (une série quotidienne n'envahit pas la liste). */
    public const SERIES_DAYS = 7;

    /** Types d'événements dont la validation alimente le carnet de santé : affichés en premier. */
    public const HEALTH_TYPES = ['veterinary', 'farrier', 'care'];

    private array $memo = [];

    public function __construct(private HorseAccess $access, private CalendarService $calendar) {}

    /**
     * Éléments à valider : ['kind' => 'event'|'session', 'at' => Carbon, 'event'|'session' => modèle, 'date' => occurrence d'une série],
     * les rendez-vous de soin d'abord, puis les plus récents.
     */
    public function items(User $user, ?Horse $horse = null): Collection
    {
        return $this->memo[$user->id.':'.($horse?->id ?? 'all')] ??= $this->events($user, $horse)->concat($this->sessions($user, $horse))
            ->sortBy([fn ($a, $b) => $this->rank($a) <=> $this->rank($b), fn ($a, $b) => $b['at'] <=> $a['at']])->values();
    }

    /** Remise à zéro (début de requête, après une validation). */
    public function flush(): void
    {
        $this->memo = [];
    }

    public function count(User $user, ?Horse $horse = null): int
    {
        return $this->items($user, $horse)->count();
    }

    /** Portée de l'accueil : le cheval actif s'il y en a un, sinon tous les chevaux. */
    public function scopeFor(User $user): ?Horse
    {
        return $this->access->focusHorse($user);
    }

    private function rank(array $item): int
    {
        return match (true) {
            $item['kind'] === 'event' && (in_array($item['event']->type, self::HEALTH_TYPES, true) || $item['event']->source_type) => 0,
            $item['kind'] === 'session' => 1,
            default => 2,
        };
    }

    private function events(User $user, ?Horse $horse): Collection
    {
        $horseIds = $this->editableHorses($user, $horse, Perm::CALENDAR_EDIT);
        // Événements sans cheval (écurie) : seulement sur « tous les chevaux », dans les espaces où la personne gère l'agenda.
        $orgIds = $horse ? [] : $user->memberships->filter(fn ($m) => $m->organization && in_array(Perm::CALENDAR_EDIT, $m->role->permissionKeys(), true)
            && app(Entitlements::class)->writable($m->organization))->pluck('organization_id')->all();
        if (! $horseIds && ! $orgIds) {
            return collect();
        }
        $now = now();
        $from = $now->copy()->subDays(self::DAYS)->startOfDay();
        $events = CalendarEvent::with('horse')
            ->where(fn ($q) => $q->whereIn('horse_id', $horseIds)->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)))
            ->whereIn('status', ['planned', 'confirmed'])
            // Séances : leur bilan est proposé à part. Traitements : simples repères de début et de fin.
            ->where(fn ($q) => $q->whereNull('source_type')->orWhereNotIn('source_type', [(new RidingSession)->getMorphClass(), (new Treatment)->getMorphClass()]))
            ->whereNull('treatment_id')->where('type', '!=', 'treatment')
            ->where(fn ($q) => $q->where(fn ($o) => $o->whereNull('recurrence')->whereBetween('starts_at', [$from, $now]))
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where('starts_at', '<=', $now)
                    ->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $now->copy()->subDays(self::SERIES_DAYS)->toDateString()))))
            ->get();
        $seriesFrom = $now->copy()->subDays(self::SERIES_DAYS)->startOfDay();

        return $this->calendar->expand($events, $from, $now)
            ->filter(fn ($o) => ! $o['event']->recurrence || $o['at']->gte($seriesFrom))
            ->filter(fn ($o) => $o['event']->timeStatus($o['at'])->is('to_validate'))
            ->map(fn ($o) => ['kind' => 'event', 'at' => $o['at'], 'event' => $o['event'], 'date' => $o['event']->recurrence ? $o['at']->toDateString() : null])
            ->values();
    }

    /** Séances de la personne (cavalier ou auteur) restées « prévues » ou « en cours » après leur fin : bilan à faire. */
    private function sessions(User $user, ?Horse $horse): Collection
    {
        $horseIds = array_values(array_unique([...$this->editableHorses($user, $horse, Perm::SESSIONS_EDIT_OWN), ...$this->editableHorses($user, $horse, Perm::SESSIONS_EDIT_ALL)]));
        if (! $horseIds) {
            return collect();
        }

        return RidingSession::with(['horse', 'rider'])->whereIn('horse_id', $horseIds)->whereIn('status', ['planned', 'in_progress'])
            ->whereBetween('scheduled_at', [now()->subDays(self::DAYS)->startOfDay(), now()])
            ->where(fn ($q) => $q->where('rider_id', $user->id)->orWhere('created_by', $user->id))
            ->get()
            ->filter(fn (RidingSession $s) => $s->timeStatus()->is('to_validate'))
            ->map(fn (RidingSession $s) => ['kind' => 'session', 'at' => Carbon::parse($s->scheduled_at), 'session' => $s, 'date' => null])
            ->values();
    }

    /** Chevaux (actifs) sur lesquels la personne peut agir, limités au cheval demandé. */
    private function editableHorses(User $user, ?Horse $horse, string $permission): array
    {
        return $this->access->accessibleHorses($user)->loadMissing('organization')
            ->when($horse, fn ($c) => $c->where('id', $horse->id))
            ->filter(fn (Horse $h) => $this->access->can($user, $h, $permission))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
