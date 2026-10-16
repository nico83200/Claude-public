<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Horse;
use App\Models\OrganizationMember;
use App\Services\AgendaJournal;
use App\Services\CalendarFeed;
use App\Services\CalendarService;
use App\Services\HorseAccess;
use App\Services\ProfessionalDirectory;
use App\Services\RidingSessionService;
use App\Services\Upsell;
use App\Support\Fr;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $events = CalendarEvent::with(['horse', 'responsible', 'professional'])
            ->where(fn ($q) => $q->whereIn('horse_id', $horseIds)->orWhere(fn ($w) => $w->whereNull('horse_id')->whereIn('organization_id', $orgIds)))
            ->visibleSessions($access->horseIdsWith($user, Perm::SESSIONS_VIEW))
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('horse_id', $h))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('vue') === 'journal', fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q->whereBetween('starts_at', [$from, $to])
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where('starts_at', '<=', $to)->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $from->toDateString()))))
            ->get();

        $editableHorses = Horse::whereIn('id', $access->horseIdsWith($user, Perm::CALENDAR_EDIT))->whereNull('archived_at')->get(['id', 'official_name', 'usual_name'])->pipe(Horse::options(...));

        return view('calendar.index', [
            'view' => $view, 'date' => $date, 'from' => $from, 'to' => $to,
            // Rendez-vous et tout ce qui est daté ailleurs dans l'application (pesées, observations, dépenses…).
            'occurrences' => $this->calendar->expand($events, $from, $to)
                ->concat($request->query('vue') === 'rdv' ? [] : $journal->between($user, $from, $to, $request->integer('horse') ?: null, $request->query('type')))
                ->sortBy(fn ($o) => [$o['at']->toDateString(), $o['event']->all_day ? 0 : 1, $o['at']->format('H:i')])->values(),
            'horses' => Horse::whereIn('id', $horseIds)->get(['id', 'official_name', 'usual_name'])->pipe(Horse::options(...)),
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

        return view('calendar.show', [
            'event' => $event->load(['horse', 'responsible', 'professional', 'careRecord.category', 'treatment']),
            'canEdit' => $this->canEdit($request, $event),
            'siblings' => $event->groupSiblings()->filter(fn ($e) => $e->horse && $this->horseCan($e->horse, Perm::CALENDAR_VIEW))->values(),
            'canValidate' => $event->horse && in_array($event->type, ['veterinary', 'farrier', 'care'], true) && in_array($event->status, ['planned', 'confirmed'], true)
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

        return redirect()->route('calendar.index', ['date' => $event->starts_at->toDateString(), 'view' => $request->input('return_view', 'month')])->with('success', '« '.$event->title.' » ajouté le '.$event->starts_at->translatedFormat('D j M').($event->all_day ? '' : ' à '.$event->starts_at->format('H:i')).'.')->with('highlight', $event->id);
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

        return redirect()->route('calendar.show', $event)->with('success', 'Événement mis à jour.'.($moved ? ' Les '.Fr::count($moved, 'autre cheval', 'autres chevaux').' du rendez-vous suivent.' : ''));
    }

    public function destroy(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event), 403);
        abort_if($event->isSessionEvent(), 422, 'Cet événement suit la séance : supprimez ou annulez la séance.');
        if ($event->treatment && $event->treatment->administrations()->exists()) {
            return back()->with('error', 'Ce traitement a déjà des administrations enregistrées : arrêtez-le depuis la page Santé du cheval pour garder son historique.');
        }
        $event->delete();

        return redirect()->route('calendar.index')->with('success', 'Événement supprimé.');
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
        ]);
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
