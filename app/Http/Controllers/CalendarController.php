<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Horse;
use App\Models\OrganizationMember;
use App\Models\Professional;
use App\Services\CalendarService;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    public function index(Request $request, HorseAccess $access)
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
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('horse_id', $h))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->where(fn ($q) => $q->whereBetween('starts_at', [$from, $to])
                ->orWhere(fn ($r) => $r->whereNotNull('recurrence')->where('starts_at', '<=', $to)->where(fn ($u) => $u->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $from->toDateString()))))
            ->get();

        $editableHorses = Horse::whereIn('id', $access->horseIdsWith($user, Perm::CALENDAR_EDIT))->whereNull('archived_at')->orderBy('official_name')->pluck('official_name', 'id');

        return view('calendar.index', [
            'view' => $view, 'date' => $date, 'from' => $from, 'to' => $to,
            'occurrences' => $this->calendar->expand($events, $from, $to),
            'horses' => Horse::whereIn('id', $horseIds)->orderBy('official_name')->pluck('official_name', 'id'),
            'editableHorses' => $editableHorses,
            'professionals' => Professional::whereIn('organization_id', $orgIds)->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
        ]);
    }

    public function show(Request $request, CalendarEvent $event)
    {
        $this->authorizeEvent($request, $event, Perm::CALENDAR_VIEW);
        $orgIds = $request->user()->memberships->pluck('organization_id');

        return view('calendar.show', [
            'event' => $event->load(['horse', 'responsible', 'professional']),
            'canEdit' => $this->canEdit($request, $event),
            'professionals' => Professional::whereIn('organization_id', $orgIds)->whereNull('archived_at')->orderBy('last_name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->fullName()]),
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
        $event = new CalendarEvent($data);
        $event->organization_id = $orgId;
        $event->created_by = $request->user()->id;
        $event->save();

        return redirect()->route('calendar.index', ['date' => $event->starts_at->toDateString(), 'view' => $request->input('return_view', 'month')])->with('success', 'Événement ajouté.');
    }

    public function update(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event), 403);
        $data = $this->validated($request) + $request->validate(['status' => ['required', Rule::in(array_keys(CalendarEvent::STATUSES))]]);
        if (! empty($data['horse_id']) && (int) $data['horse_id'] !== (int) $event->horse_id) {
            $this->authorizeHorse(Horse::findOrFail($data['horse_id']), Perm::CALENDAR_EDIT);
        }
        $event->update($data);

        return redirect()->route('calendar.show', $event)->with('success', 'Événement mis à jour.');
    }

    public function destroy(Request $request, CalendarEvent $event)
    {
        abort_unless($this->canEdit($request, $event), 403);
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
        if (! empty($data['professional_id']) && ! Professional::whereIn('organization_id', $orgIds)->whereKey($data['professional_id'])->exists()) {
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
