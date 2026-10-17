<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\Horse;
use App\Models\OrganizationMember;
use App\Models\Treatment;
use App\Services\AgendaJournal;
use App\Services\CalendarFeed;
use App\Services\CalendarService;
use App\Services\CompetitionVaccines;
use App\Services\HorseAccess;
use App\Services\LessonCosts;
use App\Services\ProfessionalDirectory;
use App\Services\RidingDays;
use App\Services\RidingSessionService;
use App\Services\ToValidate;
use App\Services\Upsell;
use App\Support\Fr;
use App\Support\Perm;
use App\Support\VaccineRules;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function index(Request $request, HorseAccess $access, AgendaJournal $journal)
    {
        $user = $request->user();
        $view = in_array($request->query('view'), ['day', 'week', 'month'], true) ? $request->query('view') : 'month';
        $date = rescue(fn () => Carbon::parse($request->query('date', 'today')), now(), false);
        [$from, $to] = match ($view) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            default => [$date->copy()->startOfMonth()->startOfWeek(), $date->copy()->endOfMonth()->endOfWeek()],
        };

        $horseIds = $access->horseIdsWith($user, Perm::CALENDAR_VIEW);
        $orgIds = $user->memberships->pluck('organization_id');
        $events = CalendarEvent::with(['horse', 'responsible', 'professional', 'organization'])
            ->where(fn ($q) => $q->whereIn('horse_id', $horseIds)->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)))
            ->visibleSessions($access->horseIdsWith($user, Perm::SESSIONS_VIEW))
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('horse_id', $h))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('vue') === 'journal', fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q->whereBetween('starts_at', [$from, $to])
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where('starts_at', '<=', $to)->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $from->toDateString()))))
            ->get();

        // Jours de monte (chevaux partagés) : « Jour de Léa » sur chaque journée attribuée.
        $ridingHorses = Horse::with('organization')->whereIn('id', $request->integer('horse') ? array_intersect($horseIds, [$request->integer('horse')]) : $horseIds)->whereNull('archived_at')->get();
        $editableHorses = Horse::whereIn('id', $access->horseIdsWith($user, Perm::CALENDAR_EDIT))->whereNull('archived_at')->get(['id', 'official_name', 'usual_name'])->pipe(Horse::options(...));

        return view('calendar.index', [
            'view' => $view, 'date' => $date, 'from' => $from, 'to' => $to,
            // Glissés de la liste (téléphone) : droits de modification de chaque événement.
            'canEditEvent' => fn (CalendarEvent $e) => $this->canEdit($request, $e),
            // Concours à venir : vaccins du cheval selon le règlement choisi (pour qui voit sa santé).
            'vaccineCheck' => fn (CalendarEvent $e, Carbon $at) => $e->type === 'competition' && $e->horse && $access->can($user, $e->horse, Perm::HEALTH_VIEW)
                ? app(CompetitionVaccines::class)->forEvent($e, $at) : null,
            // Rendez-vous et tout ce qui est daté ailleurs dans l'application (pesées, observations, dépenses…).
            'occurrences' => $this->calendar->expand($events, $from, $to)
                ->concat($request->query('vue') === 'rdv' ? [] : $journal->between($user, $from, $to, $request->integer('horse') ?: null, $request->query('type')))
                ->sortBy(fn ($o) => [$o['at']->toDateString(), $o['event']->all_day ? 0 : 1, $o['at']->format('H:i')])->values(),
            'horses' => Horse::whereIn('id', $horseIds)->get(['id', 'official_name', 'usual_name'])->pipe(Horse::options(...)),
            'ridingDays' => $request->query('vue') === 'journal' ? [] : app(RidingDays::class)->between($ridingHorses, $from, $to),
            'ridingHorses' => $ridingHorses->keyBy('id'),
            'editableHorses' => $editableHorses,
            'professionals' => app(ProfessionalDirectory::class)->visibleQuery($request->user())->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
            // Synchronisation Google / Apple (offres payantes), sinon proposition d'offre.
            'sync' => ($feed = app(CalendarFeed::class))->enabledFor($user) ? $feed->urls($user) : null,
            'syncUpsell' => $this->currentOrganization() ? app(Upsell::class)->for($this->currentOrganization(), $user, 'calendar_sync') : null,
        ]);
    }

    public function show(Request $request, CalendarEvent $event)
    {
        // Une séance s'ouvre sur sa propre fiche (droits « séances » vérifiés là-bas).
        if ($event->isSessionEvent() && $event->source_id) {
            return redirect()->route('sessions.show', $event->source_id);
        }
        $this->authorizeEvent($request, $event, Perm::CALENDAR_VIEW);
        $orgIds = $request->user()->memberships->pluck('organization_id');
        // Série : la fiche porte sur l'occurrence ouverte depuis l'agenda (?date=), si elle en fait partie.
        $occurrence = $event->recurrence && is_string($request->query('date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('date')) && $event->occursOn($request->query('date'))
            ? Carbon::parse($request->query('date').' '.$event->starts_at->format('H:i:s')) : null;
        $canEdit = $this->canEdit($request, $event);
        $event->loadMissing('horse');
        $vaccines = $event->type === 'competition' && $event->horse && $this->horseCan($event->horse, Perm::HEALTH_VIEW)
            ? app(CompetitionVaccines::class)->forEvent($event, $occurrence) : null;

        return view('calendar.show', [
            'vaccines' => $vaccines,
            'vaccineLink' => $vaccines && $this->horseCan($event->horse, Perm::CALENDAR_EDIT) ? app(CompetitionVaccines::class)->appointmentUrl($vaccines) : null,
            'canEditHealth' => $event->horse && $this->horseCan($event->horse, Perm::HEALTH_EDIT),
            // Coaching du concours (frais de cours de la personne qui paie).
            'coaching' => $event->type === 'competition' ? app(LessonCosts::class)->visibleEventCharges($event, $request->user()) : collect(),
            'event' => $event->load(['horse', 'responsible', 'professional', 'careRecord.category', 'treatment']),
            'canEdit' => $canEdit,
            'occurrence' => $occurrence,
            'deleteChoices' => $occurrence && $canEdit && self::swipeDeletable($event) ? self::deleteChoices($event, $occurrence->toDateString()) : [],
            'canMarkDone' => $canEdit && ! $event->isSessionEvent() && in_array($event->status, ['planned', 'confirmed'], true)
                && ($event->recurrence ? $occurrence && $occurrence->isPast() : $event->starts_at->isPast()),
            'siblings' => $event->groupSiblings()->filter(fn ($e) => $e->horse && $this->horseCan($e->horse, Perm::CALENDAR_VIEW))->values(),
            'canValidate' => ! $event->recurrence && $event->horse && in_array($event->type, ['veterinary', 'farrier', 'care'], true) && in_array($event->status, ['planned', 'confirmed'], true)
                && $this->horseCan($event->horse, Perm::HEALTH_EDIT),
            'professionals' => app(ProfessionalDirectory::class)->visibleQuery($request->user())->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
        ]);
    }

    /** Modification d'un événement (page séparée de la fiche). */
    public function edit(Request $request, CalendarEvent $event)
    {
        if ($event->isSessionEvent() && $event->source_id) {
            return redirect()->route('sessions.details', $event->source_id);
        }
        $this->authorizeEvent($request, $event, Perm::CALENDAR_VIEW);
        abort_unless($this->canEdit($request, $event), 403);
        $orgIds = $request->user()->memberships->pluck('organization_id');

        return view('calendar.edit', [
            'event' => $event->load(['horse', 'careRecord']),
            'professionals' => app(ProfessionalDirectory::class)->visibleQuery($request->user())->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if (! empty($data['horse_id'])) {
            $horse = Horse::findOrFail($data['horse_id']);
            $this->authorizeHorse($horse, Perm::CALENDAR_EDIT);
            $orgId = $horse->organization_id;
        } else {
            $org = $this->currentOrganization();
            $this->authorizeOrg($org, Perm::CALENDAR_EDIT);
            $orgId = $org->id;
        }
        // Une séance, un cours ou une balade d'un cheval saisi dans l'agenda devient une vraie séance (visible dans Séances).
        $sessionType = ['session' => 'free', 'lesson' => 'private_lesson', 'ride' => 'ride'][$data['type']] ?? null;
        if ($sessionType && isset($horse) && empty($data['recurrence']) && $this->horseCan($horse, Perm::SESSIONS_CREATE)) {
            $session = app(RidingSessionService::class)->create($horse, $request->user(), [
                'scheduled_at' => $data['starts_at'], 'session_type' => $sessionType, 'objective' => $data['title'],
                'planned_minutes' => min(600, (int) $data['duration_minutes']), 'notes' => $data['notes'] ?? null,
                'rider_id' => $data['responsible_user_id'] ?? null,
            ]);

            return redirect()->route('calendar.index', ['date' => $data['starts_at']->toDateString(), 'view' => $request->input('return_view', 'month')])
                ->with('success', 'Séance ajoutée à l\'agenda et au suivi des séances.');
        }
        $event = new CalendarEvent($data);
        $event->organization_id = $orgId;
        $event->created_by = $request->user()->id;
        $event->save();

        return redirect()->route('calendar.index', ['date' => $event->starts_at->toDateString(), 'view' => $request->input('return_view', 'month')])->with('success', '« '.$event->title.' » ajouté le '.$event->starts_at->translatedFormat('D j M').($event->all_day ? '' : ' à '.$event->starts_at->format('H:i')).'.')->with('highlight', $event->id)
            ->with($this->vaccineWarning($event));
    }

    public function update(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event), 403);
        abort_if($event->isSessionEvent(), 422, 'Cet événement suit la séance : modifiez la séance.');
        $data = $this->validated($request) + $request->validate(['status' => ['required', Rule::in(array_keys(CalendarEvent::STATUSES))]]);
        if (! empty($data['horse_id']) && (int) $data['horse_id'] !== (int) $event->horse_id) {
            $this->authorizeHorse(Horse::findOrFail($data['horse_id']), Perm::CALENDAR_EDIT);
        }
        $event->update($data);
        // Rendez-vous groupé : même jour, même heure et même intervenant pour les autres chevaux (si demandé).
        $moved = 0;
        if ($event->group_uuid && $request->boolean('whole_group')) {
            foreach ($event->groupSiblings() as $sibling) {
                if ($sibling->horse && $this->horseCan($sibling->horse, Perm::CALENDAR_EDIT)) {
                    $sibling->update(collect($data)->only(['starts_at', 'all_day', 'duration_minutes', 'professional_id'])->all());
                    $moved++;
                }
            }
        }

        return redirect()->route('calendar.show', $event)->with('success', 'Événement mis à jour.'.($moved ? ' Les '.Fr::count($moved, 'autre cheval', 'autres chevaux').' du rendez-vous suivent.' : ''))
            ->with($this->vaccineWarning($event));
    }

    public function destroy(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event), 403);
        abort_if($event->isSessionEvent(), 422, 'Cet événement suit la séance : supprimez ou annulez la séance.');
        if ($event->treatment && $event->treatment->administrations()->exists()) {
            return back()->with('error', 'Ce traitement a déjà des administrations enregistrées : arrêtez-le depuis la page Santé du cheval pour garder son historique.');
        }
        // Événement récurrent : cette occurrence, cette occurrence et les suivantes, ou toute la série.
        if ($event->recurrence && in_array($request->input('scope'), ['occurrence', 'following'], true)) {
            $date = $this->occurrenceDate($request, $event);

            return $request->input('scope') === 'occurrence'
                ? $this->deleteOccurrence($request, $event, $date)
                : $this->deleteFollowing($request, $event, $date);
        }
        $event->delete();
        $text = $event->recurrence ? 'Série « '.$event->title.' » supprimée' : 'Événement supprimé';
        // Glissé dans la liste (téléphone) : réponse JSON avec l'adresse d'annulation.
        if ($request->wantsJson()) {
            return response()->json(['restore_url' => route('calendar.restore', $event->id), 'text' => $text, 'reload' => (bool) $event->recurrence]);
        }

        return redirect()->route('calendar.index')->with('success', $text.'.');
    }

    /** Une occurrence retirée de la série (les autres restent). */
    private function deleteOccurrence(Request $request, CalendarEvent $event, string $date)
    {
        $event->exclude($date);
        $event->save();
        $text = 'Occurrence du '.Carbon::parse($date)->format('d/m').' supprimée';

        return $request->wantsJson()
            ? response()->json(['restore_url' => route('calendar.occurrence.restore', [$event, 'date' => $date]), 'text' => $text])
            : redirect()->route('calendar.index', ['date' => $date])->with('success', $text.'.');
    }

    /** Cette occurrence et les suivantes : la série s'arrête à l'occurrence précédente (ou disparaît si c'est la première). */
    private function deleteFollowing(Request $request, CalendarEvent $event, string $date)
    {
        $previous = $event->occurrencesBetween($event->starts_at->copy()->startOfDay(), Carbon::parse($date)->subDay()->endOfDay())->last();
        if (! $previous) {
            $request->merge(['scope' => 'series']);

            return $this->destroy($request, $event);
        }
        $before = $event->recurrence_until?->toDateString();
        $event->update(['recurrence_until' => $previous->toDateString()]);
        $text = 'Série « '.$event->title.' » arrêtée au '.$previous->format('d/m/Y');

        return $request->wantsJson()
            ? response()->json(['restore_url' => route('calendar.recurrence.until', array_filter([$event, 'until' => $before])), 'text' => $text, 'reload' => true])
            : redirect()->route('calendar.index', ['date' => $date])->with('success', $text.'.');
    }

    /** « Annuler » : l'occurrence revient dans la série. */
    public function restoreOccurrence(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event), 403);
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        $event->reinclude($date);
        $event->save();

        return $request->wantsJson() ? response()->json(['ok' => true]) : redirect()->route('calendar.index', ['date' => $date]);
    }

    /** « Annuler » : la série reprend sa date de fin précédente (aucune si vide). */
    public function restoreUntil(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event) && $event->recurrence, 403);
        $until = $request->validate(['until' => ['nullable', 'date_format:Y-m-d']])['until'] ?? null;
        $event->update(['recurrence_until' => $until]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : redirect()->route('calendar.show', $event);
    }

    /** Date d'occurrence demandée (?date=AAAA-MM-JJ), qui doit appartenir à la série. */
    private function occurrenceDate(Request $request, CalendarEvent $event): string
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        abort_unless($event->occursOn($date), 422, 'Cette date ne fait pas partie de la série.');

        return $date;
    }

    /** « Annuler » après un glissé : l'événement revient. */
    public function restore(Request $request, int $eventId)
    {
        $event = CalendarEvent::onlyTrashed()->findOrFail($eventId);
        abort_unless($this->canEdit($request, $event), 403);
        $event->restore();

        return $request->wantsJson() ? response()->json(['ok' => true]) : redirect()->route('calendar.show', $event)->with('success', 'Événement restauré.');
    }

    /**
     * Événement passé validé d'un geste (glissé vers la droite, liste « À valider ») : « Réalisé », comme depuis sa modification
     * (un rendez-vous de soin rejoint alors le carnet de santé). Pour une série, seule l'occurrence du jour est validée :
     * elle devient un événement à part, retiré de la série.
     */
    public function markDone(Request $request, CalendarEvent $event)
    {
        return $this->settle($request, $event, 'done');
    }

    /** Rendez-vous passé qui n'a pas eu lieu : « Annulé », gardé dans l'historique (même règle pour une série). */
    public function cancel(Request $request, CalendarEvent $event)
    {
        return $this->settle($request, $event, 'cancelled');
    }

    private function settle(Request $request, CalendarEvent $event, string $status)
    {
        abort_unless($this->canEdit($request, $event), 403);
        abort_if($event->isSessionEvent(), 422, 'Cet événement suit la séance : '.($status === 'done' ? 'validez' : 'annulez').' la séance.');
        abort_unless(in_array($event->status, ['planned', 'confirmed'], true), 422, 'Cet événement est déjà validé ou annulé.');
        $verb = $status === 'done' ? 'validé' : 'annulé';
        if ($event->recurrence) {
            $date = $this->occurrenceDate($request, $event);
            $at = Carbon::parse($date.' '.$event->starts_at->format('H:i:s'));
            abort_if($at->isFuture(), 422, 'Cette occurrence n\'a pas encore eu lieu.');
            $copy = DB::transaction(function () use ($event, $date, $at, $status) {
                $copy = $event->replicate(['uuid', 'recurrence', 'recurrence_interval', 'recurrence_until', 'recurrence_exceptions', 'group_uuid']);
                $copy->uuid = (string) Str::uuid();
                $copy->forceFill(['starts_at' => $at, 'status' => $status, 'recurrence_parent_id' => $event->id, 'organization_id' => $event->organization_id, 'created_by' => $event->created_by])->save();
                $event->exclude($date);
                $event->save();

                return $copy;
            });
            $text = '« '.$event->title.' » du '.$at->format('d/m').' '.$verb;
            if (! $request->wantsJson() && ! $request->boolean('stay')) {
                return redirect()->route('calendar.show', $copy)->with('success', $text.'.');
            }
        } elseif ($status === 'cancelled' && $event->source_type === (new CareRecord)->getMorphClass()) {
            // Contrôle prévu qui n'a pas eu lieu : l'événement annulé reste dans l'historique, le contrôle redevient « à prévoir ».
            $event->forceFill(['status' => 'cancelled', 'source_type' => null, 'source_id' => null])->saveQuietly();
            $text = '« '.$event->title.' » annulé : contrôle à reprogrammer';
        } else {
            $event->update(['status' => $status]);
            $text = '« '.$event->title.' » '.$verb;
        }
        app(ToValidate::class)->flush();

        return $request->wantsJson() ? response()->json(['text' => $text]) : back()->with('success', $text.'.');
    }

    /** Choix de suppression d'une occurrence de série (glissé dans la liste, fiche de l'occurrence). */
    public static function deleteChoices(CalendarEvent $event, string $date): array
    {
        $day = Carbon::parse($date);

        return [
            ['scope' => 'occurrence', 'label' => 'Cette occurrence', 'help' => 'Seulement le '.$day->translatedFormat('j F').'.', 'url' => route('calendar.destroy', [$event, 'scope' => 'occurrence', 'date' => $date])],
            ['scope' => 'following', 'label' => 'Cette occurrence et les suivantes', 'help' => 'La série s\'arrête à l\'occurrence précédente.', 'url' => route('calendar.destroy', [$event, 'scope' => 'following', 'date' => $date])],
            ['scope' => 'series', 'label' => 'Toute la série', 'help' => 'Toutes les occurrences, passées comprises.', 'url' => route('calendar.destroy', [$event, 'scope' => 'series'])],
        ];
    }

    /** Suppression d'un glissé : seulement si elle ne retire rien d'autre (soin du carnet, traitement). */
    public static function swipeDeletable(CalendarEvent $event): bool
    {
        return ! $event->isSessionEvent() && ! $event->care_record_id && ! $event->treatment_id
            && ! in_array($event->source_type, [(new CareRecord)->getMorphClass(), (new Treatment)->getMorphClass()], true);
    }

    private function authorizeEvent(Request $request, CalendarEvent $event, string $perm): void
    {
        if ($event->horse) {
            $this->authorizeHorse($event->horse, $perm);
        } else {
            abort_unless($request->user()->memberships->contains('organization_id', $event->organization_id), 404);
        }
    }

    private function canEdit(Request $request, CalendarEvent $event): bool
    {
        return $event->horse ? $this->horseCan($event->horse, Perm::CALENDAR_EDIT)
            : $request->user()->canInOrg($event->organization_id, Perm::CALENDAR_EDIT) && $this->entitlements()->writable($event->organization);
    }

    /** Concours enregistré : message si les vaccins du cheval sont à revoir (pour qui voit sa santé). */
    private function vaccineWarning(CalendarEvent $event): array
    {
        $event->load('horse'); // le cheval a pu changer
        if ($event->type !== 'competition' || ! $event->horse || ! $this->horseCan($event->horse, Perm::HEALTH_VIEW)) {
            return [];
        }
        $vaccines = app(CompetitionVaccines::class);
        $vaccines->flush();
        $check = $vaccines->forEvent($event);

        return $check && ! in_array($check['state'], ['ok', 'planned'], true) ? ['warning' => 'Vaccins de '.$event->horse->shortName().' pour ce concours : '.lcfirst($vaccines->headline($check))] : [];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'horse_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(array_keys(CalendarEvent::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'all_day' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recurrence' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:date'],
            'vaccine_rules' => ['nullable', Rule::in(array_keys(VaccineRules::options()))],
            'coaching_rounds' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $data['vaccine_rules'] = $data['type'] === 'competition' ? ($data['vaccine_rules'] ?? null) : null;
        $data['coaching_rounds'] = $data['type'] === 'competition' ? ($data['coaching_rounds'] ?? null) : null;
        // Les intervenants et responsables doivent appartenir aux espaces de l'utilisateur.
        $orgIds = $request->user()->memberships->pluck('organization_id');
        if (! empty($data['professional_id']) && ! app(ProfessionalDirectory::class)->visibleQuery($request->user())->whereKey($data['professional_id'])->exists()) {
            $data['professional_id'] = null;
        }
        if (! empty($data['responsible_user_id']) && ! OrganizationMember::whereIn('organization_id', $orgIds)->where('user_id', $data['responsible_user_id'])->exists()) {
            $data['responsible_user_id'] = null;
        }
        $data['starts_at'] = Carbon::parse($data['date'].' '.($data['time'] ?? '09:00'));
        $data['all_day'] = (bool) ($data['all_day'] ?? false);
        $data['duration_minutes'] = $data['duration_minutes'] ?? 60;
        $data['recurrence_interval'] = $data['recurrence_interval'] ?? 1;
        unset($data['date'], $data['time']);

        return $data;
    }
}
