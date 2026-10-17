<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\User;
use App\Support\Perm;
use App\Support\VaccineRules;
use App\Support\Vaccines;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vaccins avant un concours, d'après le carnet de santé (vaccinations faites, vaccinations et rendez-vous vétérinaires
 * prévus) : le cheval est-il en règle le jour du concours selon le règlement choisi (FFE, FEI, voir VaccineRules) ?
 * C'est une aide à la préparation : la référence reste le passeport du cheval.
 *
 * États : ok (en règle), planned (rendez-vous prévu à temps), conflict (vaccin prévu trop près du concours),
 * unsure (vaccination du carnet sans précision qui pourrait suffire), ko (vaccin manquant, en retard ou trop récent).
 */
class CompetitionVaccines
{
    /** Accueil : concours des N prochains jours dont les vaccins sont à revoir. */
    public const ALERT_DAYS = 45;

    /** Écart entre les deux injections d'une primo-vaccination (au plus large des règlements). */
    private const PRIMO_GAP = [21, 92];

    private array $memo = [];

    public function __construct(private HorseAccess $access) {}

    public function flush(): void
    {
        $this->memo = [];
    }

    /** Concours à venir d'un cheval (ni annulé ni réalisé) : vérification, ou null (pas un concours, pas de vérification). */
    public function forEvent(CalendarEvent $event, ?Carbon $at = null): ?array
    {
        $at = ($at ?? $event->starts_at)->copy()->startOfDay();
        if ($event->type !== 'competition' || ! $event->horse_id || in_array($event->status, ['done', 'cancelled'], true) || $at->lt(today())) {
            return null;
        }
        $event->loadMissing('horse');

        return $event->horse ? $this->check($event->horse, $at, $event->vaccine_rules) : null;
    }

    /** Vérification pour un concours le $day selon le règlement $ruleKey (null : règlement par défaut), ou null sans vérification. */
    public function check(Horse $horse, Carbon $day, ?string $ruleKey = null): ?array
    {
        $rule = VaccineRules::get($ruleKey);
        if (! $rule) {
            return null;
        }
        $day = $day->copy()->startOfDay();
        $records = $this->records($horse);
        $items = [];
        foreach ($rule['vaccines'] as $key => $p) {
            $given = collect($records['done'])->filter(fn ($r) => in_array($key, $r['keys'], true) && $r['date']->lte($day))->values();
            $doses = $given->pluck('date');
            // Une seule injection connue, qui n'est pas notée comme un rappel : peut-être la 1re d'une primo-vaccination.
            $single = $given->count() === 1 && ! str_contains(Str::ascii(mb_strtolower($given[0]['care']->reason.' '.$given[0]['care']->care_performed)), 'rappel');
            $item = ['key' => $key, 'name' => mb_strtolower(Vaccines::TYPES[$key]), 'min_days' => (int) $p['min_days'], 'single' => $single]
                + $this->evaluate($doses, $day, $p);
            $planned = collect($records['planned'])->filter(fn ($r) => in_array($key, $r['keys'], true) && $r['date']->lte($day));
            // Injection prévue trop près du concours : le cheval ne pourrait pas concourir.
            $item['close'] = $planned->first(fn ($r) => $r['date']->copy()->addDays($item['min_days'])->gt($day));
            // Vaccin manquant ou rappel en retard : rendez-vous prévu assez tôt pour être en règle.
            $item['planned'] = in_array($item['state'], ['missing', 'expired'], true)
                ? $planned->first(fn ($r) => $r['date']->copy()->addDays($item['min_days'])->lte($day)) : null;
            $item['status'] = $item['close'] ? 'conflict' : ($item['planned'] ? 'planned' : $item['state']);
            $items[$key] = $item;
        }
        $need = collect($items)->filter(fn ($i) => in_array($i['status'], ['missing', 'expired'], true));
        // Dernier jour pour faire le vaccin à temps.
        $deadline = $need->isNotEmpty() ? $day->copy()->subDays($need->max('min_days')) : null;
        // Vaccination du carnet sans précision, plus récente que le dernier vaccin connu : peut-être celle qui manque.
        $unknown = $need->isEmpty() ? collect() : collect($records['unknown'])
            ->filter(fn ($r) => $r['date']->lte($day) && $need->contains(fn ($i) => ! $i['last'] || $r['date']->gt($i['last'])))->values();
        $statuses = collect($items)->pluck('status');
        $state = match (true) {
            $statuses->contains('too_recent') => 'ko',
            $need->isNotEmpty() => $unknown->isNotEmpty() ? 'unsure' : 'ko',
            $statuses->contains('conflict') => 'conflict',
            $statuses->contains('planned') => 'planned',
            default => 'ok',
        };

        return ['rule' => $rule, 'horse' => $horse, 'day' => $day, 'state' => $state, 'items' => $items, 'unknown' => $unknown,
            'deadline' => $deadline, 'late' => $deadline !== null && $deadline->lt(today())];
    }

    /**
     * Dernier vaccin : en règle s'il est assez ancien (au moins N jours) et encore valable le jour du concours.
     * Deux injections à 21-92 jours d'intervalle (sans vaccin dans l'année qui précède) forment une primo-vaccination :
     * la 2e ne vaut que jusqu'au 1er rappel.
     */
    private function evaluate(Collection $doses, Carbon $day, array $p): array
    {
        if ($doses->isEmpty()) {
            return ['state' => 'missing', 'last' => null, 'until' => null, 'from' => null, 'primo' => false];
        }
        $n = $doses->count();
        $last = $doses[$n - 1];
        $prev = $n >= 2 ? $doses[$n - 2] : null;
        $gap = $prev ? (int) $prev->diffInDays($last) : null;
        $primo = $prev && $gap >= self::PRIMO_GAP[0] && $gap <= self::PRIMO_GAP[1] && ($n === 2 || $doses[$n - 3]->copy()->addMonths(12)->lt($prev));
        $until = $primo
            ? $last->copy()->addMonths((int) $p['primo_months'])->addDays((int) $p['primo_days'])
            : $last->copy()->addMonths((int) $p['months'])->addDays((int) $p['days']);
        $from = $last->copy()->addDays((int) $p['min_days']);

        return ['state' => $day->lt($from) ? 'too_recent' : ($day->gt($until) ? 'expired' : 'ok'), 'last' => $last, 'until' => $until, 'from' => $from, 'primo' => $primo];
    }

    /** Vaccinations du cheval : faites, prévues (soin daté dans le futur, rendez-vous vétérinaire « vaccin »), sans précision. */
    private function records(Horse $horse): array
    {
        return $this->memo['horse:'.$horse->id] ??= $this->load($horse);
    }

    private function load(Horse $horse): array
    {
        $categories = $this->memo['categories'] ??= CareCategory::whereIn('key', ['vaccination', 'veterinary'])->pluck('key', 'id')->all();
        $done = $planned = $unknown = [];
        foreach (CareRecord::where('horse_id', $horse->id)->whereIn('care_category_id', array_keys($categories))->orderBy('performed_at')->get() as $care) {
            $vaccination = ($categories[$care->care_category_id] ?? null) === 'vaccination';
            // Soin vétérinaire : compté seulement s'il parle d'un vaccin (« rappel grippe »), pas d'une grippe soignée.
            if (! $vaccination && ! Vaccines::mentioned($care->reason, $care->care_performed, $care->observations)) {
                continue;
            }
            $keys = Vaccines::of($care);
            $item = ['date' => $care->performed_at->copy()->startOfDay(), 'keys' => $keys, 'care' => $care, 'event' => null];
            if (! $keys) {
                if ($vaccination && $care->performed_at->lte(now())) {
                    $unknown[] = $item;
                }

                continue;
            }
            $care->performed_at->lte(now()) ? $done[] = $item : $planned[] = $item;
        }
        // Rendez-vous vétérinaire de l'agenda pour un vaccin, pas encore enregistré dans le carnet.
        $events = CalendarEvent::where('horse_id', $horse->id)->where('type', 'veterinary')->whereIn('status', ['planned', 'confirmed'])
            ->whereNull('care_record_id')->whereNull('recurrence')->where('starts_at', '>=', today())->orderBy('starts_at')->get();
        foreach ($events as $event) {
            if ($keys = array_values(array_intersect(Vaccines::infer($event->title, $event->notes), VaccineRules::VACCINES))) {
                $planned[] = ['date' => $event->starts_at->copy()->startOfDay(), 'keys' => $keys, 'care' => null, 'event' => $event];
            }
        }
        usort($planned, fn ($a, $b) => $a['date'] <=> $b['date']);

        return ['done' => $done, 'planned' => $planned, 'unknown' => $unknown];
    }

    /** Accueil : concours des prochaines semaines dont les vaccins sont à revoir (chevaux dont on voit la santé et l'agenda). */
    public function alerts(User $user, ?Horse $horse = null): Collection
    {
        $ids = array_map('intval', array_values(array_intersect($this->access->horseIdsWith($user, Perm::HEALTH_VIEW), $this->access->horseIdsWith($user, Perm::CALENDAR_VIEW))));
        if ($horse) {
            $ids = in_array((int) $horse->id, $ids, true) ? [(int) $horse->id] : [];
        }
        if (! $ids) {
            return collect();
        }

        return CalendarEvent::with('horse')->whereIn('horse_id', $ids)->where('type', 'competition')->whereIn('status', ['planned', 'confirmed'])->whereNull('recurrence')
            ->whereBetween('starts_at', [today(), today()->addDays(self::ALERT_DAYS)->endOfDay()])->orderBy('starts_at')->get()
            ->map(fn (CalendarEvent $e) => ['event' => $e, 'check' => $this->forEvent($e)])
            ->filter(fn ($a) => $a['check'] && in_array($a['check']['state'], ['ko', 'unsure', 'conflict'], true))->values();
    }

    /** « Prendre rendez-vous » : nouvel événement vétérinaire prérempli (vaccins à faire, vétérinaire habituel, date proposée). */
    public function appointmentUrl(array $check): ?string
    {
        if (! $check['deadline'] || $check['late']) {
            return null;
        }
        $horse = $check['horse'];
        $keys = collect($check['items'])->filter(fn ($i) => in_array($i['status'], ['missing', 'expired'], true))->keys()->all();
        $date = today()->addDays(3);
        $date = $date->gt($check['deadline']) ? $check['deadline']->copy() : $date;
        $vet = CareRecord::where('horse_id', $horse->id)->whereNotNull('professional_id')
            ->whereIn('care_category_id', CareCategory::whereIn('key', ['vaccination', 'veterinary'])->select('id'))->latest('performed_at')->value('professional_id');

        return route('calendar.index', array_filter(['nouveau' => 1, 'type' => 'veterinary', 'horse' => $horse->id,
            'title' => 'Vaccin '.Vaccines::names($keys).' – '.$horse->shortName(), 'professional' => $vet, 'date' => $date->toDateString()]));
    }

    /** Ligne d'un vaccin sur la fiche du concours. */
    public function line(array $item, array $check): string
    {
        $d = fn (?Carbon $c) => $c?->translatedFormat('j F Y');
        $name = ucfirst($item['name']);
        $horse = $check['horse']->shortName();
        $arrival = ! empty($check['rule']['arrival']);
        $text = match ($item['state']) {
            'missing' => $name.' : aucun vaccin dans le carnet.',
            'expired' => $name.' : '.($item['primo'] ? 'la primo-vaccination (2e injection le ' : 'le rappel du ').$d($item['last']).($item['primo'] ? ')' : '')
                .' n\'est valable que jusqu\'au '.$d($item['until']).($item['primo'] ? ', 1er rappel à faire.' : '.'),
            'too_recent' => $name.' : vaccin du '.$d($item['last']).' trop proche du concours ; '.$horse.' peut '.($arrival ? 'arriver sur le concours' : 'concourir').' à partir du '.$d($item['from']).'.',
            default => $name.' : '.($item['primo'] ? '2e injection de la primo-vaccination le ' : 'rappel du ').$d($item['last']).', valable jusqu\'au '.$d($item['until']).'.',
        };
        if ($item['planned']) {
            $text .= ' Rendez-vous prévu le '.$d($item['planned']['date']).' : '.$horse.' sera en règle s\'il a lieu.';
        }
        if ($item['close']) {
            $text .= ' Le vaccin prévu le '.$d($item['close']['date']).' tombe moins de '.$item['min_days'].' jours avant '.($arrival ? 'l\'arrivée sur le concours' : 'le concours').' : '.$horse.' ne pourrait pas '.($arrival ? 'y entrer' : 'concourir').'.';
        }

        return $text;
    }

    /** Résumé en une ou deux phrases (notification, accueil, message après l'enregistrement du concours). */
    public function headline(array $check): string
    {
        if ($check['state'] === 'ok') {
            return 'Vaccins en règle.';
        }
        $d = fn (Carbon $c) => $c->translatedFormat('j F');
        $parts = collect($check['items'])->map(fn ($i) => match ($i['status']) {
            'missing' => 'aucun vaccin contre la '.$i['name'].' dans le carnet',
            'expired' => ($i['primo'] ? '1er rappel ' : 'rappel ').$i['name'].' à faire',
            'too_recent' => 'vaccin '.$i['name'].' trop récent (possible à partir du '.$d($i['from']).')',
            'conflict' => 'vaccin '.$i['name'].' prévu trop près du concours',
            'planned' => 'vaccin '.$i['name'].' prévu le '.$d($i['planned']['date']),
            default => null,
        })->filter()->values();
        if ($check['unknown']->isNotEmpty()) {
            $parts->push('vaccination du '.$d($check['unknown']->last()['date']).' à préciser');
        }
        $text = ucfirst($parts->implode(' ; ')).'.';
        if ($check['deadline']) {
            $text .= $check['late']
                ? ' Trop tard pour ce concours : il faut au moins '.collect($check['items'])->max('min_days').' jours entre l\'injection et '.(! empty($check['rule']['arrival']) ? 'l\'arrivée.' : 'le concours.')
                : ' À faire au plus tard le '.$d($check['deadline']).'.';
        }

        return $text;
    }

    /** Pastille : couleur et texte. */
    public static function badge(array $check): array
    {
        return match ($check['state']) {
            'ok' => ['green', 'Vaccins en règle'],
            'planned' => ['blue', 'Vaccin prévu'],
            'unsure' => ['amber', 'Vaccins à préciser'],
            default => ['red', 'Vaccins à vérifier'],
        };
    }
}
