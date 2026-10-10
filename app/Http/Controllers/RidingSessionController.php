<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\SessionComment;
use App\Models\SessionTemplate;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\HorseAccess;
use App\Services\RidingSessionService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RidingSessionController extends Controller
{
    public function __construct(private RidingSessionService $sessions) {}

    public function index(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $horseIds = $access->horseIdsWith($user, Perm::SESSIONS_VIEW);
        $query = RidingSession::with(['horse', 'rider'])->withCount(['items as done_count' => fn ($q) => $q->where('status', 'done')])
            ->whereIn('horse_id', $horseIds)
            ->when($request->integer('horse'), fn ($q, $h) => $q->where('horse_id', $h))
            ->when($request->integer('rider'), fn ($q, $r) => $q->where('rider_id', $r))
            ->when($request->query('discipline'), fn ($q, $d) => $q->where('discipline', $d))
            ->when($request->query('type'), fn ($q, $t) => $q->where('session_type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('from'), fn ($q, $f) => $q->where('scheduled_at', '>=', Carbon::parse($f)->startOfDay()))
            ->when($request->query('to'), fn ($q, $t) => $q->where('scheduled_at', '<=', Carbon::parse($t)->endOfDay()));

        $stats = null;
        if ($request->integer('horse') && in_array($request->integer('horse'), $horseIds, true)) {
            $stats = $this->stats($request->integer('horse'));
        }

        return view('sessions.index', [
            'sessions' => $query->orderByDesc('scheduled_at')->paginate(20)->withQueryString(),
            'horses' => Horse::whereIn('id', $horseIds)->orderBy('official_name')->pluck('official_name', 'id'),
            'riders' => User::whereIn('id', RidingSession::whereIn('horse_id', $horseIds)->whereNotNull('rider_id')->distinct()->pluck('rider_id'))->orderBy('name')->pluck('name', 'id'),
            'stats' => $stats,
            'canCreate' => ! empty($access->horseIdsWith($user, Perm::SESSIONS_CREATE)),
        ]);
    }

    /** Indicateurs de progression pour un cheval. */
    private function stats(int $horseId): array
    {
        $done = RidingSession::where('horse_id', $horseId)->where('status', 'completed');
        $since = now()->subDays(90);

        return [
            'count_90' => (clone $done)->where('scheduled_at', '>=', $since)->count(),
            'minutes_90' => (int) (clone $done)->where('scheduled_at', '>=', $since)->sum('actual_minutes'),
            'by_week' => (clone $done)->where('scheduled_at', '>=', now()->subWeeks(12))->get(['scheduled_at', 'actual_minutes'])
                ->groupBy(fn ($s) => $s->scheduled_at->startOfWeek()->format('d/m'))->map(fn ($g) => ['count' => $g->count(), 'minutes' => (int) $g->sum('actual_minutes')]),
            'by_type' => (clone $done)->where('scheduled_at', '>=', $since)->selectRaw('session_type, count(*) as c')->groupBy('session_type')->pluck('c', 'session_type'),
            'top_exercises' => \DB::table('session_exercises')->join('riding_sessions', 'riding_sessions.id', '=', 'session_exercises.riding_session_id')
                ->where('riding_sessions.horse_id', $horseId)->where('session_exercises.status', 'done')->whereNull('riding_sessions.deleted_at')
                ->selectRaw('session_exercises.name, count(*) as c, sum(session_exercises.difficulty) as difficult')->groupBy('session_exercises.name')->orderByDesc('c')->limit(8)->get(),
            'to_rework' => RidingSession::where('horse_id', $horseId)->whereNotNull('to_rework')->where('to_rework', '!=', '')->orderByDesc('scheduled_at')->limit(5)->get(['id', 'scheduled_at', 'to_rework']),
            'objectives' => RidingSession::where('horse_id', $horseId)->whereNotNull('objective')->where('scheduled_at', '>=', $since)->selectRaw('objective, count(*) as c')->groupBy('objective')->orderByDesc('c')->limit(5)->pluck('c', 'objective'),
        ];
    }

    public function create(Request $request, HorseAccess $access)
    {
        $user = $request->user();
        $horses = Horse::whereIn('id', $access->horseIdsWith($user, Perm::SESSIONS_CREATE))->whereNull('archived_at')->orderBy('official_name')->get()
            ->filter(fn ($h) => $access->can($user, $h, Perm::SESSIONS_CREATE));
        abort_if($horses->isEmpty(), 403, 'Vous ne pouvez créer de séance pour aucun cheval.');

        return view('sessions.create', [
            'horses' => $horses->pluck('official_name', 'id'),
            'templates' => SessionTemplate::where('user_id', $user->id)->orWhereIn('organization_id', $user->memberships->pluck('organization_id'))->orderBy('name')->pluck('name', 'id'),
            'selectedHorse' => $request->integer('horse') ?: null,
            'date' => $request->query('date'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(RidingSessionService::rules() + ['horse_id' => ['required', 'integer'], 'template_id' => ['nullable', 'integer']]);
        $horse = Horse::findOrFail($data['horse_id']);
        $this->authorizeHorse($horse, Perm::SESSIONS_CREATE);
        unset($data['horse_id']);

        if (! empty($data['template_id'])) {
            $template = SessionTemplate::where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereIn('organization_id', $request->user()->memberships->pluck('organization_id')))->findOrFail($data['template_id']);
            unset($data['template_id']);
            $session = $this->sessions->fromTemplate($template, $horse, $request->user(), array_filter($data, fn ($v) => $v !== null));
        } else {
            unset($data['template_id']);
            $session = $this->sessions->create($horse, $request->user(), $data);
        }

        return redirect()->route('sessions.edit', $session)->with('success', 'Séance créée : ajoutez les exercices.');
    }

    public function show(Request $request, RidingSession $session)
    {
        $this->authorizeHorse($session->horse, Perm::SESSIONS_VIEW);
        $session->load(['horse', 'rider', 'creator', 'exercises.exercise', 'comments.author']);
        $previous = RidingSession::where('horse_id', $session->horse_id)->where('status', 'completed')->where('scheduled_at', '<', $session->scheduled_at)->orderByDesc('scheduled_at')->limit(3)->get();

        return view('sessions.show', [
            'session' => $session,
            'previous' => $previous,
            'canEdit' => $this->sessions->canEdit($request->user(), $session),
            'canComment' => $this->horseCan($session->horse, Perm::COMMENTS_CREATE),
            'canCreate' => $this->horseCan($session->horse, Perm::SESSIONS_CREATE),
        ]);
    }

    public function edit(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        abort_if($session->isCompleted(), 422, 'Une séance terminée ne peut plus être préparée : dupliquez-la pour en créer une nouvelle.');
        $user = $request->user();

        return view('sessions.edit', [
            'session' => $session->load(['horse', 'exercises']),
            'library' => Exercise::visibleTo($user)->whereNull('archived_at')->with('category')->orderBy('name')->get(),
            'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'),
            'riders' => $this->ridersFor($session->horse),
        ]);
    }

    public function update(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        $data = $request->validate(RidingSessionService::rules() + ['status' => ['nullable', 'in:planned,in_progress,cancelled']]);
        if ($session->isCompleted()) {
            // Après clôture, seules les notes restent modifiables.
            $data = array_intersect_key($data, array_flip(['notes']));
        }
        $session->update($this->sessions->sanitizeRider($session->horse, $data, $request->user()));
        $this->notifyChange($request, $session);

        return back()->with('success', 'Séance enregistrée.');
    }

    public function destroy(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        $horse = $session->horse;
        $session->delete();

        return redirect()->route('sessions.index', ['horse' => $horse->id])->with('success', 'Séance supprimée.');
    }

    /** Mode séance mobile (fonctionne aussi hors ligne via l'application écurie). */
    public function live(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        if ($session->status === 'planned') {
            $session->update(['status' => 'in_progress']);
        }

        return view('sessions.live', ['session' => $session->load(['horse', 'exercises'])]);
    }

    public function debrief(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);

        return view('sessions.debrief', ['session' => $session->load(['horse', 'exercises'])]);
    }

    public function saveDebrief(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        $data = $request->validate(RidingSessionService::debriefRules());
        $this->sessions->complete($session, $data);

        if (! empty($data['anomalies'])) {
            ObservationController::notifyManagers($session->horse, $request->user(), app(HorseAccess::class), 'Anomalie signalée : '.$session->horse->shortName(), 'Une anomalie a été signalée lors d\'une séance par '.$request->user()->name.'.');
        }

        return redirect()->route('sessions.show', $session)->with('success', 'Bilan enregistré, séance clôturée.');
    }

    public function duplicate(Request $request, RidingSession $session)
    {
        $this->authorizeHorse($session->horse, Perm::SESSIONS_VIEW);
        $this->authorizeHorse($session->horse, Perm::SESSIONS_CREATE);
        $copy = $this->sessions->duplicate($session->load('exercises'), $request->user(), $request->input('scheduled_at'));

        return redirect()->route('sessions.edit', $copy)->with('success', 'Nouvelle séance créée à partir de la séance du '.$session->scheduled_at->format('d/m/Y').'. L\'originale est inchangée.');
    }

    public function comment(Request $request, RidingSession $session)
    {
        $this->authorizeHorse($session->horse, Perm::SESSIONS_VIEW);
        $this->authorizeHorse($session->horse, Perm::COMMENTS_CREATE);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        SessionComment::create(['riding_session_id' => $session->id, 'author_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('success', 'Commentaire ajouté.');
    }

    private function authorizeEdit(Request $request, RidingSession $session): void
    {
        $this->authorizeHorse($session->horse, Perm::SESSIONS_VIEW);
        if (! $this->sessions->canEdit($request->user(), $session)) {
            abort(403, app(HorseAccess::class)->denialReason($request->user(), $session->horse, Perm::SESSIONS_EDIT_OWN));
        }
    }

    private function ridersFor(Horse $horse)
    {
        $access = app(HorseAccess::class);
        $ids = $horse->organization->users()->pluck('users.id')
            ->merge($horse->accessGrants()->active()->pluck('user_id'))
            ->merge($horse->ownerships()->whereNotNull('user_id')->pluck('user_id'))->unique();

        return User::whereIn('id', $ids)->orderBy('name')->get()->filter(fn ($u) => $access->permissions($u, $horse))->pluck('name', 'id');
    }

    /** Prévient le propriétaire / créateur d'une modification importante faite par un tiers. */
    private function notifyChange(Request $request, RidingSession $session): void
    {
        if (! $session->wasChanged(['scheduled_at', 'status', 'rider_id'])) {
            return;
        }
        $targets = collect([$session->creator, $session->rider])->filter()->unique('id')->reject(fn ($u) => $u->id === $request->user()->id);
        foreach ($targets as $user) {
            if (in_array(Perm::SESSIONS_VIEW, app(HorseAccess::class)->permissions($user, $session->horse), true)) {
                $user->notify(new AppNotification('session_changed', 'Séance modifiée', 'La séance du '.$session->scheduled_at->format('d/m/Y H:i').' avec '.$session->horse->shortName().' a été modifiée par '.$request->user()->name.'.', route('sessions.show', $session), false));
            }
        }
    }
}
