<?php

namespace App\Http\Controllers;

use App\Models\AssistantRequest;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\SessionComment;
use App\Models\SessionTemplate;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AppNotification;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\ClaudeGateway;
use App\Services\DebriefQuestions;
use App\Services\ExerciseSuggestions;
use App\Services\HorseAccess;
use App\Services\RidingSessionService;
use App\Support\Perm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

        $sessions = $query->orderByDesc('scheduled_at')->paginate(20)->withQueryString();

        return view('sessions.index', [
            'sessions' => $sessions,
            // Séances que l'utilisateur peut supprimer (glisser vers la gauche sur téléphone).
            'deletable' => $sessions->getCollection()->filter(fn ($s) => $this->sessions->canEdit($user, $s))->pluck('id')->all(),
            'horses' => Horse::whereIn('id', $horseIds)->orderBy('official_name')->pluck('official_name', 'id'),
            'riders' => User::whereIn('id', RidingSession::whereIn('horse_id', $horseIds)->whereNotNull('rider_id')->distinct()->pluck('rider_id'))->orderBy('name')->pluck('name', 'id'),
            'stats' => $stats,
            // Ouvert depuis le dossier d'un cheval : la page garde l'en-tête et les onglets du cheval.
            'contextHorse' => $stats !== null ? Horse::find($request->integer('horse')) : null,
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

        if ($session->isCompleted()) {
            return redirect()->route('sessions.debrief', $session)->with('success', 'Séance passée enregistrée comme terminée : notez les exercices travaillés et votre bilan.');
        }

        return redirect()->route('sessions.edit', $session)->with('success', 'Séance créée : ajoutez les exercices.');
    }

    public function show(Request $request, RidingSession $session)
    {
        $this->authorizeHorse($session->horse, Perm::SESSIONS_VIEW);
        if ($request->boolean('enregistree')) {
            // Retour de la préparation : les exercices sont déjà enregistrés au fil de l'eau.
            session()->now('success', 'Séance enregistrée. Après la séance, faites directement le bilan.');
        }
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

        $library = Exercise::visibleTo($user)->whereNull('archived_at')->with('category')->orderBy('name')->get();
        $session->load(['horse', 'exercises', 'rider.profile']);

        return view('sessions.edit', [
            'session' => $session,
            'library' => $library,
            // Exercices habituels du cavalier et exercices adaptés à son galop, par étape.
            'suggestions' => $suggestions = app(ExerciseSuggestions::class)->forSession($session, $user->loadMissing('profile'), $library),
            // Galop inconnu : suggestions préparées pour chaque niveau, affichées dès que le cavalier choisit son galop (sans recharger).
            'suggestionVariants' => $suggestions['rider']?->id === $user->id && ! $suggestions['galop']
                ? collect([2, 4, 6])->mapWithKeys(fn ($g) => [(string) $g => app(ExerciseSuggestions::class)->forSession($session, $user, $library, $g)])->all()
                : [],
            'categories' => ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'),
            'riders' => $this->ridersFor($session->horse),
            'phasePriority' => self::phasePriority(ExerciseCategory::orderBy('sort_order')->pluck('name', 'id')),
        ]);
    }

    /**
     * Ordre des catégories dans la fenêtre de choix, selon l'étape : échauffement et assouplissements d'abord
     * pour l'échauffement, retour au calme pour la fin, travail principal ensuite. Les autres suivent dans leur ordre habituel.
     *
     * @return array<string, array<int, int>> étape => [id de catégorie => rang]
     */
    public static function phasePriority($categories): array
    {
        $first = [
            'warmup' => ['échauffement', 'assouplissement', 'travail à pied', 'longe', 'transitions'],
            'main' => ['travail sur le plat', 'figures de manège', 'transitions', 'impulsion', 'mobilité latérale', 'obstacle', 'barres au sol'],
            'complementary' => ['barres au sol', 'équilibre', 'rectitude', 'mobilité latérale', 'travail à pied', 'longe', 'extérieur'],
            'cooldown' => ['retour au calme', 'assouplissement', 'extérieur'],
        ];
        $out = [];
        foreach ($first as $phase => $names) {
            $rank = [];
            foreach ($categories as $id => $name) {
                $pos = array_search(mb_strtolower($name), $names, true);
                $rank[$id] = $pos === false ? 100 + count($rank) : $pos;
            }
            $out[$phase] = $rank;
        }

        return $out;
    }

    /** Modifier les informations de la séance (date, type, cavalier, lieu…) sans passer par la préparation. */
    public function details(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);

        return view('sessions.details', ['session' => $session->load('horse'), 'riders' => $this->ridersFor($session->horse)]);
    }

    public function update(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        // Après clôture, seules les notes restent modifiables.
        $data = $session->isCompleted()
            ? $request->validate(['notes' => ['nullable', 'string', 'max:10000']])
            : $request->validate(RidingSessionService::rules() + ['status' => ['nullable', 'in:planned,in_progress,cancelled']]);
        $session->update($this->sessions->sanitizeRider($session->horse, $data, $request->user()));
        $this->notifyChange($request, $session);

        return ($request->input('return_to') === 'show' ? redirect()->route('sessions.show', $session) : back())->with('success', 'Séance enregistrée.');
    }

    public function destroy(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        $horse = $session->horse;
        $session->delete();
        // Suppression d'un glissé dans la liste (téléphone) : réponse JSON avec l'adresse d'annulation.
        if ($request->expectsJson()) {
            return response()->json(['deleted' => true, 'restore_url' => route('sessions.restore', $session)]);
        }

        return redirect()->route('sessions.index', ['horse' => $horse->id])->with('success', 'Séance supprimée.');
    }

    /** « Annuler » après une suppression : la séance (et son événement d'agenda) revient. */
    public function restore(Request $request, RidingSession $session)
    {
        abort_unless($session->trashed(), 404);
        $this->authorizeEdit($request, $session);
        $session->restore();

        return $request->expectsJson()
            ? response()->json(['restored' => true])
            : redirect()->route('sessions.show', $session)->with('success', 'Séance restaurée.');
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

        return view('sessions.debrief', [
            'session' => $session->load(['horse', 'exercises']),
            'questions' => app(DebriefQuestions::class)->for($session),
            'library' => Exercise::visibleTo($request->user())->whereNull('archived_at')->orderBy('name')->pluck('name'),
        ]);
    }

    public function saveDebrief(Request $request, RidingSession $session)
    {
        $this->authorizeEdit($request, $session);
        $data = $request->validate(RidingSessionService::debriefRules());
        // Bilan en questions à choix : les réponses remplacent les champs texte.
        if ($request->boolean('questionnaire')) {
            $answers = $request->validate([
                'q_objective' => ['nullable', 'in:atteint,en partie,pas encore'],
                'q_good' => ['nullable', 'array', 'max:20'], 'q_good.*' => ['string', 'max:150'],
                'q_hard' => ['nullable', 'array', 'max:20'], 'q_hard.*' => ['string', 'max:150'],
                'q_rework' => ['nullable', 'array', 'max:20'], 'q_rework.*' => ['string', 'max:150'],
                'q_anomalies' => ['nullable', 'array', 'max:20'], 'q_anomalies.*' => ['string', 'max:150'],
                'q_next' => ['nullable', 'array', 'max:20'], 'q_next.*' => ['string', 'max:160'],
                'q_comment' => ['nullable', 'string', 'max:500'],
            ]);
            $data = app(DebriefQuestions::class)->compose($answers, $session) + $data;
        }
        $work = $request->validate([
            'items_reviewed' => ['nullable', 'boolean'],
            'done' => ['nullable', 'array', 'max:100'], 'done.*' => ['integer'],
            'added' => ['nullable', 'array', 'max:30'],
            'added.*.name' => ['nullable', 'string', 'max:150'],
            'added.*.minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
        ]);
        $this->sessions->recordWork($session, $request->user(), $request->boolean('items_reviewed') ? array_map('intval', $work['done'] ?? []) : null, $work['added'] ?? []);
        $this->sessions->complete($session, $data);

        if (! empty($data['anomalies'])) {
            ObservationController::notifyManagers($session->horse, $request->user(), app(HorseAccess::class), 'Anomalie signalée : '.$session->horse->shortName(), 'Une anomalie a été signalée lors d\'une séance par '.$request->user()->name.' : '.$data['anomalies'].'.', route('sessions.show', $session));
        }
        $this->notifyOwnersOfDebrief($request->user(), $session->fresh());

        return redirect()->route('sessions.show', $session)->with('success', 'Bilan enregistré, séance clôturée.');
    }

    /** Bilan fait par un autre cavalier que le propriétaire : le propriétaire est prévenu, avec le lien pour le consulter. */
    private function notifyOwnersOfDebrief(User $author, RidingSession $session): void
    {
        $horse = $session->horse;
        $access = app(HorseAccess::class);
        $owners = User::whereIn('id', $horse->ownerships()->whereNotNull('user_id')->whereNull('ended_on')->pluck('user_id'))->get();
        if ($owners->isEmpty()) {
            // Sans propriétaire déclaré : les personnes qui gèrent le cheval dans l'espace.
            $owners = $horse->organization->users()->get()->filter(fn ($u) => in_array(Perm::HORSE_MANAGE, $access->permissions($u, $horse), true));
        }
        $feeling = $session->rider_feeling ? ' · ressenti '.$session->rider_feeling.'/5' : '';
        $summary = collect([$session->progress, $session->difficulties ? 'Difficile : '.$session->difficulties : null])->filter()->implode(' ');
        foreach ($owners->unique('id')->reject(fn ($u) => $u->id === $author->id) as $owner) {
            if (! in_array(Perm::SESSIONS_VIEW, $access->permissions($owner, $horse), true)) {
                continue;
            }
            $owner->notify(new AppNotification('session_report', 'Bilan de séance : '.$horse->shortName(),
                $author->name.' a fait le bilan de « '.$session->title().' » du '.$session->scheduled_at->translatedFormat('j F').$feeling.'.'.($summary ? ' '.Str::limit($summary, 160) : ''),
                route('sessions.show', $session), mail: false));
        }
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

    /**
     * Plan de séance proposé par l'assistant (Claude) : objectif, durée, niveau du cavalier,
     * derniers bilans. Rien n'est ajouté : la personne choisit ensuite les exercices à garder.
     */
    public function plan(Request $request, RidingSession $session, AssistantService $assistant, ClaudeGateway $claude)
    {
        $this->authorizeEdit($request, $session);
        abort_if($session->isCompleted(), 422);
        $org = $session->horse->organization;
        if ($reason = $assistant->unavailableReason($org)) {
            return response()->json(['error' => $reason], 422);
        }
        $user = $request->user()->loadMissing('profile');
        $session->load(['horse.breed', 'rider.profile', 'exercises']);
        $rider = $session->rider ?? ($session->rider_name ? null : $user);
        $galop = $rider?->profile?->galop();
        $recent = RidingSession::where('horse_id', $session->horse_id)->where('status', 'completed')->where('id', '!=', $session->id)
            ->orderByDesc('scheduled_at')->limit(4)->get();
        $horse = $session->horse;
        $context = implode("\n", array_filter([
            'Cheval : '.$horse->shortName().' · '.(Horse::DISCIPLINES[$horse->main_discipline] ?? 'discipline non précisée').' · niveau de travail : '.($horse->work_level ?: 'non précisé').($horse->age() ? ' · '.$horse->age().' ans' : ''),
            $horse->precautions ? 'Précautions : '.$horse->precautions : null,
            'Cavalier : '.($galop ? 'galop '.$galop.' ('.UserProfile::exerciseLevel($galop).')' : 'niveau inconnu'),
            'Séance : '.(RidingSession::TYPES[$session->session_type] ?? $session->session_type).($session->discipline ? ' · '.(Horse::DISCIPLINES[$session->discipline] ?? $session->discipline) : '').' · durée prévue '.($session->planned_minutes ?: 45).' minutes',
            'Objectif : '.($session->objective ?: 'non précisé (travail général adapté)'),
            $session->exercises->isNotEmpty() ? 'Déjà prévu : '.$session->exercises->pluck('name')->implode(', ') : null,
            $recent->isNotEmpty() ? "Dernières séances :\n".$recent->map(fn ($s) => '- '.$s->scheduled_at->format('d/m').' '.($s->objective ?: '').($s->progress ? ' · progrès : '.$s->progress : '').($s->difficulties ? ' · difficultés : '.$s->difficulties : '').($s->to_rework ? ' · à retravailler : '.$s->to_rework : ''))->implode("\n") : null,
        ]));
        $library = Exercise::visibleTo($user)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
        $started = microtime(true);
        try {
            $result = $claude->planSession($context, $library->pluck('name')->all());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        AssistantRequest::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'organization_id' => $org->id, 'conversation_id' => (string) Str::uuid(),
            'input_mode' => 'text', 'input_text' => 'Plan de séance : '.($session->objective ?: $session->title()), 'reply' => Str::limit((string) ($result['data']['summary'] ?? ''), 500), 'status' => 'done',
            'model' => $result['model'], 'input_tokens' => $result['usage']['input'], 'output_tokens' => $result['usage']['output'], 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
        $byName = $library->keyBy(fn ($e) => mb_strtolower(trim($e->name)));
        $items = [];
        foreach (array_keys(RidingSession::PHASES) as $phase) {
            foreach ((array) ($result['data'][$phase] ?? []) as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $match = $byName->get(mb_strtolower($name));
                $items[] = ['exercise_id' => $match?->id, 'name' => $match?->name ?? mb_substr($name, 0, 150), 'phase' => $phase, 'phase_label' => RidingSession::PHASES[$phase],
                    'planned_minutes' => max(0, min(300, (int) ($row['minutes'] ?? 0))) ?: null, 'instructions' => mb_substr((string) ($row['note'] ?? ''), 0, 500), 'in_library' => (bool) $match];
            }
        }

        return response()->json(['summary' => (string) ($result['data']['summary'] ?? ''), 'items' => $items, 'minutes' => array_sum(array_column($items, 'planned_minutes'))]);
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
