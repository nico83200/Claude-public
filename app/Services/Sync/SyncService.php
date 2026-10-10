<?php

namespace App\Services\Sync;

use App\Http\Controllers\DailyLogController;
use App\Models\DailyLog;
use App\Models\Exercise;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\SessionComment;
use App\Models\SessionExercise;
use App\Models\SyncConflict;
use App\Models\SyncCursor;
use App\Models\SyncDevice;
use App\Models\SyncOperation;
use App\Models\User;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use App\Services\HorseService;
use App\Services\RidingSessionService;
use App\Support\Perm;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Synchronisation bidirectionnelle hors ligne.
 *
 * Principes :
 *  - chaque opération client possède un op_uuid → traitement idempotent ;
 *  - chaque création porte un uuid généré côté client → jamais de doublon ;
 *  - les droits et l'état de la licence sont revérifiés pour chaque opération ;
 *  - les modifications utilisent une fusion à trois voies (base / serveur / local) :
 *    un champ n'est écrasé que si la valeur serveur est encore celle connue du
 *    client ; sinon un conflit explicite est enregistré (jamais d'écrasement silencieux) ;
 *  - les ajouts (commentaires, journaux, observations, exercices) sont fusionnés.
 */
class SyncService
{
    public function __construct(
        private HorseAccess $access,
        private Entitlements $entitlements,
        private RidingSessionService $sessions,
    ) {}

    public function device(User $user, string $deviceUuid, ?string $label, ?string $userAgent): SyncDevice
    {
        $device = SyncDevice::firstOrCreate(['user_id' => $user->id, 'device_uuid' => $deviceUuid], ['label' => $label, 'user_agent' => substr((string) $userAgent, 0, 250)]);
        $device->update(['last_seen_at' => now()] + ($label ? ['label' => $label] : []));

        return $device;
    }

    // ------------------------------------------------------------------ PULL

    public function pull(User $user, SyncDevice $device, bool $ackWipe): array
    {
        if ($ackWipe && $device->wipe_requested_at) {
            $device->update(['wipe_requested_at' => null]);
        }
        if ($device->wipe_requested_at) {
            return ['wipe' => true, 'reason' => 'Purge des données locales demandée.'];
        }

        $selected = DB::table('offline_horse_selections')->where('user_id', $user->id)->pluck('horse_id');
        $horses = Horse::with(['breed', 'identifiers', 'organization'])->whereIn('id', $selected)->whereNull('archived_at')->get()
            ->filter(fn (Horse $h) => in_array(Perm::HORSE_VIEW, $this->access->permissions($user, $h), true))->values();

        $payload = [
            'wipe' => false,
            'server_time' => now()->toIso8601String(),
            'valid_until' => now()->addHours(config('equine.offline.ttl_hours'))->toIso8601String(),
            'user' => ['id' => $user->id, 'name' => $user->name],
            'horse_ids' => $horses->pluck('id')->all(),
            'horses' => [], 'sessions' => [], 'events' => [], 'treatments' => [], 'feeding' => [],
            'exercises' => Exercise::visibleTo($user)->whereNull('archived_at')->with('category')->orderBy('name')->get()->map(fn ($e) => [
                'id' => $e->id, 'name' => $e->name, 'category' => $e->category?->name, 'discipline' => $e->discipline, 'level' => $e->level,
                'objective' => $e->objective, 'instructions' => $e->instructions, 'duration_minutes' => $e->duration_minutes,
                'repetitions' => $e->repetitions, 'vigilance' => $e->vigilance, 'success_criteria' => $e->success_criteria, 'version' => $e->version,
            ])->all(),
            'open_conflicts' => SyncConflict::where('user_id', $user->id)->where('status', 'open')->count(),
        ];

        foreach ($horses as $horse) {
            $perms = $this->access->permissions($user, $horse);
            $has = fn ($p) => in_array($p, $perms, true);
            $writable = $this->entitlements->writable($horse->organization);
            $payload['horses'][] = [
                'id' => $horse->id, 'uuid' => $horse->uuid, 'version' => $horse->version,
                'official_name' => $horse->official_name, 'usual_name' => $horse->usual_name,
                'breed' => $horse->breed?->name, 'sex' => Horse::SEXES[$horse->sex], 'age' => $horse->age(), 'coat' => $horse->coat,
                'sire' => $horse->identifier('sire'), 'particularities' => $horse->particularities,
                'general_notes' => $horse->general_notes, 'current_location' => $horse->current_location,
                'care_instructions' => $horse->care_instructions, 'precautions' => $horse->precautions,
                'organization' => $horse->organization->name,
                'permissions' => $writable ? $perms : array_values(array_filter($perms, fn ($p) => Perm::isRead($p))),
                'writable' => $writable,
            ];
            if ($has(Perm::SESSIONS_VIEW)) {
                $recent = RidingSession::with(['items', 'comments.author', 'rider'])->where('horse_id', $horse->id)
                    ->where(fn ($q) => $q->whereIn('status', ['planned', 'in_progress'])->where('scheduled_at', '>=', now()->subDays(14))
                        ->orWhere(fn ($w) => $w->where('status', 'completed')->where('scheduled_at', '>=', now()->subDays(120))))
                    ->orderByDesc('scheduled_at')->limit(config('equine.offline.recent_sessions'))->get();
                foreach ($recent as $s) {
                    $payload['sessions'][] = $this->sessionPayload($s, $user);
                }
            }
            if ($has(Perm::CALENDAR_VIEW)) {
                foreach ($horse->events()->where('status', '!=', 'cancelled')->whereBetween('starts_at', [now()->subDays(7), now()->addDays(30)])->orderBy('starts_at')->get() as $e) {
                    $payload['events'][] = ['id' => $e->id, 'uuid' => $e->uuid, 'horse_id' => $horse->id, 'type' => $e->type, 'title' => $e->title, 'starts_at' => $e->starts_at->toIso8601String(), 'duration_minutes' => $e->duration_minutes, 'status' => $e->status, 'notes' => $e->notes];
                }
            }
            if ($has(Perm::TREATMENTS_VIEW)) {
                foreach ($horse->treatments()->current()->get() as $t) {
                    $payload['treatments'][] = ['id' => $t->id, 'horse_id' => $horse->id, 'product' => $t->product, 'dosage' => $t->dosage, 'frequency' => $t->frequency, 'starts_on' => $t->starts_on->toDateString(), 'ends_on' => $t->ends_on?->toDateString(), 'instructions' => $t->instructions];
                }
            }
            if ($has(Perm::FEEDING_VIEW) && ($plan = $horse->feedingPlans()->with('entries')->whereNull('ends_on')->first())) {
                $payload['feeding'][] = ['horse_id' => $horse->id, 'name' => $plan->name, 'instructions' => $plan->instructions, 'entries' => $plan->entries->map(fn ($e) => $e->only(['feed', 'quantity', 'unit', 'time_of_day', 'frequency', 'is_supplement', 'note']))->all()];
            }
        }

        $device->update(['last_pull_at' => now()]);
        SyncCursor::updateOrCreate(['sync_device_id' => $device->id, 'scope' => 'all'], ['cursor' => now()]);

        return $payload;
    }

    public function sessionPayload(RidingSession $s, User $user): array
    {
        return [
            'id' => $s->id, 'uuid' => $s->uuid, 'horse_id' => $s->horse_id, 'version' => $s->version,
            'scheduled_at' => $s->scheduled_at->toIso8601String(), 'session_type' => $s->session_type, 'discipline' => $s->discipline,
            'objective' => $s->objective, 'planned_minutes' => $s->planned_minutes, 'actual_minutes' => $s->actual_minutes,
            'location' => $s->location, 'horse_state_before' => $s->horse_state_before, 'precautions' => $s->precautions, 'notes' => $s->notes,
            'status' => $s->status, 'rider' => $s->riderLabel(), 'can_edit' => $this->sessions->canEdit($user, $s),
            'rider_feeling' => $s->rider_feeling, 'horse_behavior' => $s->horse_behavior, 'concentration' => $s->concentration, 'availability' => $s->availability,
            'difficulties' => $s->difficulties, 'progress' => $s->progress, 'to_rework' => $s->to_rework, 'anomalies' => $s->anomalies, 'next_objectives' => $s->next_objectives,
            'items' => $s->items->sortBy(fn ($i) => [array_search($i->phase, array_keys(RidingSession::PHASES)), $i->position])->values()->map(fn ($i) => $i->only([
                'uuid', 'exercise_id', 'phase', 'position', 'is_break', 'name', 'planned_minutes', 'planned_repetitions', 'instructions',
                'status', 'done_repetitions', 'actual_minutes', 'difficulty', 'note',
            ]))->all(),
            'comments' => $s->comments->map(fn ($c) => ['uuid' => $c->uuid, 'body' => $c->body, 'author' => $c->author?->name, 'created_at' => $c->created_at->toIso8601String()])->all(),
        ];
    }

    // ------------------------------------------------------------------ PUSH

    /** @return array<int, array> résultats par opération */
    public function push(User $user, SyncDevice $device, array $operations): array
    {
        $results = [];
        foreach ($operations as $op) {
            $results[] = $this->applyOne($user, $device, $op);
        }

        return $results;
    }

    private function applyOne(User $user, SyncDevice $device, array $op): array
    {
        $opUuid = $op['op_uuid'] ?? null;
        if (! is_string($opUuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $opUuid)) {
            return ['op_uuid' => $opUuid, 'status' => 'rejected', 'message' => 'Identifiant d\'opération invalide.'];
        }
        // Idempotence : une opération déjà traitée renvoie le même résultat.
        if ($existing = SyncOperation::where('op_uuid', $opUuid)->first()) {
            if ($existing->user_id !== $user->id) {
                return ['op_uuid' => $opUuid, 'status' => 'rejected', 'message' => 'Opération inconnue.'];
            }

            return ['op_uuid' => $opUuid, 'status' => $existing->status === 'applied' ? 'duplicate' : $existing->status] + ($existing->result ?? []);
        }

        $entity = (string) ($op['entity'] ?? '');
        $action = (string) ($op['action'] ?? '');
        $payload = is_array($op['payload'] ?? null) ? $op['payload'] : [];

        try {
            [$status, $result] = DB::transaction(fn () => $this->dispatch($user, $entity, $action, $payload, $opUuid));
        } catch (ValidationException $e) {
            [$status, $result] = ['rejected', ['message' => collect($e->errors())->flatten()->first()]];
        } catch (SyncRejected $e) {
            [$status, $result] = ['rejected', ['message' => $e->getMessage()]];
        } catch (Throwable $e) {
            report($e);

            // Erreur serveur : non enregistrée → le client réessaiera.
            return ['op_uuid' => $opUuid, 'status' => 'error', 'message' => 'Erreur temporaire du serveur, nouvelle tentative plus tard.'];
        }

        SyncOperation::create([
            'op_uuid' => $opUuid, 'user_id' => $user->id, 'sync_device_id' => $device->id, 'entity' => substr($entity, 0, 40),
            'entity_uuid' => isset($payload['uuid']) && is_string($payload['uuid']) && strlen($payload['uuid']) === 36 ? $payload['uuid'] : null,
            'action' => substr($action, 0, 40), 'payload' => $payload, 'status' => $status, 'result' => $result,
            'client_created_at' => rescue(fn () => isset($op['created_at']) ? Carbon::parse($op['created_at']) : null, null, false),
        ]);

        return ['op_uuid' => $opUuid, 'status' => $status] + $result;
    }

    private function dispatch(User $user, string $entity, string $action, array $p, string $opUuid): array
    {
        return match ("$entity.$action") {
            'session.create' => $this->sessionCreate($user, $p),
            'session.update' => $this->sessionUpdate($user, $p, $opUuid),
            'session.complete' => $this->sessionComplete($user, $p, $opUuid),
            'session_exercise.create' => $this->itemCreate($user, $p),
            'session_exercise.update' => $this->itemUpdate($user, $p, $opUuid),
            'comment.create' => $this->commentCreate($user, $p),
            'daily_log.create' => $this->dailyLogCreate($user, $p),
            'observation.create' => $this->observationCreate($user, $p),
            'horse.update' => $this->horseUpdate($user, $p, $opUuid),
            default => throw new SyncRejected('Opération non prise en charge : '.$entity.'.'.$action),
        };
    }

    private function horseFor(User $user, $horseId, string $permission): Horse
    {
        $horse = Horse::find((int) $horseId);
        if (! $horse || ! in_array(Perm::HORSE_VIEW, $this->access->permissions($user, $horse), true)) {
            throw new SyncRejected('Accès au cheval refusé ou révoqué.');
        }
        if (! $this->access->can($user, $horse, $permission)) {
            throw new SyncRejected($this->access->denialReason($user, $horse, $permission));
        }

        return $horse;
    }

    private function sessionByUuid(User $user, $uuid): RidingSession
    {
        $session = is_string($uuid) ? RidingSession::where('uuid', $uuid)->first() : null;
        if (! $session || ! in_array(Perm::SESSIONS_VIEW, $this->access->permissions($user, $session->horse), true)) {
            throw new SyncRejected('Séance introuvable ou accès révoqué.');
        }
        if (! $this->sessions->canEdit($user, $session)) {
            throw new SyncRejected($this->access->denialReason($user, $session->horse, Perm::SESSIONS_EDIT_OWN));
        }

        return $session;
    }

    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    private function sessionCreate(User $user, array $p): array
    {
        $data = $this->validate($p, ['uuid' => ['required', 'uuid'], 'horse_id' => ['required', 'integer'], 'items' => ['array', 'max:40'],
            'items.*.uuid' => ['required', 'uuid'], 'items.*.exercise_id' => ['nullable', 'integer'], 'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.phase' => ['nullable', Rule::in(array_keys(RidingSession::PHASES))], 'items.*.planned_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'items.*.planned_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'], 'items.*.instructions' => ['nullable', 'string', 'max:2000'],
            'items.*.is_break' => ['nullable', 'boolean']] + RidingSessionService::rules());

        if ($existing = RidingSession::withTrashed()->where('uuid', $data['uuid'])->first()) {
            return ['duplicate', ['message' => 'Séance déjà enregistrée.', 'id' => $existing->id, 'version' => $existing->version]];
        }
        $horse = $this->horseFor($user, $data['horse_id'], Perm::SESSIONS_CREATE);
        $items = $data['items'] ?? [];
        $fields = array_intersect_key($data, RidingSessionService::rules());
        $fields['scheduled_at'] = $this->localTime($fields['scheduled_at']);
        $session = $this->sessions->create($horse, $user, $fields, $items, $data['uuid']);

        return ['applied', ['id' => $session->id, 'version' => $session->version]];
    }

    /** Fusion à trois voies : $p = {uuid, changes: {...}, base: {...}}. */
    private function sessionUpdate(User $user, array $p, string $opUuid): array
    {
        $session = $this->sessionByUuid($user, $p['uuid'] ?? null);
        $allowed = ['scheduled_at', 'objective', 'planned_minutes', 'location', 'horse_state_before', 'precautions', 'notes', 'session_type', 'discipline', 'status'];
        $changes = array_intersect_key((array) ($p['changes'] ?? []), array_flip($allowed));
        $this->validate($changes, array_intersect_key(RidingSessionService::rules() + ['status' => ['in:planned,in_progress,cancelled']], $changes));
        if (isset($changes['scheduled_at'])) {
            $changes['scheduled_at'] = $this->localTime($changes['scheduled_at']);
        }
        if ($session->isCompleted()) {
            $changes = array_intersect_key($changes, ['notes' => true]);
        }

        return $this->threeWayMerge($user, $session, 'session', $changes, (array) ($p['base'] ?? []), $opUuid);
    }

    private function sessionComplete(User $user, array $p, string $opUuid): array
    {
        $session = $this->sessionByUuid($user, $p['uuid'] ?? null);
        $debrief = $this->validate(array_intersect_key((array) ($p['debrief'] ?? []), RidingSessionService::debriefRules()), RidingSessionService::debriefRules());
        if ($session->isCompleted()) {
            $differs = collect($debrief)->filter(fn ($v, $k) => $v !== null && (string) $session->$k !== (string) $v);
            if ($differs->isEmpty()) {
                return ['duplicate', ['message' => 'Bilan déjà enregistré.', 'version' => $session->version]];
            }
            // Deux bilans différents pour une même séance : décision explicite requise.
            $conflict = $this->recordConflict($user, $opUuid, 'session', $session, $differs->all(), $session->only($differs->keys()->all()), (int) ($p['base_version'] ?? 0));

            return ['conflict', ['message' => 'Cette séance a déjà été clôturée avec un autre bilan.', 'conflict_id' => $conflict->id]];
        }
        $this->sessions->complete($session, $debrief);

        return ['applied', ['version' => $session->fresh()->version]];
    }

    private function itemCreate(User $user, array $p): array
    {
        $data = $this->validate($p, ['session_uuid' => ['required', 'uuid'], 'uuid' => ['required', 'uuid'], 'exercise_id' => ['nullable', 'integer'], 'name' => ['nullable', 'string', 'max:150'],
            'phase' => ['nullable', Rule::in(array_keys(RidingSession::PHASES))], 'planned_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'planned_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'], 'instructions' => ['nullable', 'string', 'max:2000'], 'is_break' => ['nullable', 'boolean']]);
        if (SessionExercise::where('uuid', $data['uuid'])->exists()) {
            return ['duplicate', ['message' => 'Exercice déjà ajouté.']];
        }
        $session = $this->sessionByUuid($user, $data['session_uuid']);
        if ($session->isCompleted()) {
            throw new SyncRejected('Séance déjà clôturée : exercice non ajouté.');
        }
        $this->sessions->addItems($session, $user, [$data]);

        return ['applied', []];
    }

    private function itemUpdate(User $user, array $p, string $opUuid): array
    {
        $item = is_string($p['uuid'] ?? null) ? SessionExercise::where('uuid', $p['uuid'])->first() : null;
        if (! $item) {
            throw new SyncRejected('Exercice de séance introuvable.');
        }
        $session = $this->sessionByUuid($user, $item->session->uuid);
        if ($session->isCompleted()) {
            throw new SyncRejected('Séance déjà clôturée : modification non appliquée.');
        }
        $allowed = ['status', 'done_repetitions', 'actual_minutes', 'difficulty', 'note', 'planned_minutes', 'planned_repetitions', 'instructions', 'phase'];
        $changes = array_intersect_key((array) ($p['changes'] ?? []), array_flip($allowed));
        $this->validate($changes, ['status' => ['in:pending,done,skipped'], 'done_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'], 'actual_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'difficulty' => ['boolean'], 'note' => ['nullable', 'string', 'max:255'], 'planned_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'planned_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'], 'instructions' => ['nullable', 'string', 'max:2000'], 'phase' => [Rule::in(array_keys(RidingSession::PHASES))]]);
        if (isset($changes['status'])) {
            $changes['status_changed_at'] = now();
        }

        return $this->threeWayMerge($user, $item, 'session_exercise', $changes, (array) ($p['base'] ?? []) + ['status_changed_at' => $item->status_changed_at], $opUuid);
    }

    private function commentCreate(User $user, array $p): array
    {
        $data = $this->validate($p, ['uuid' => ['required', 'uuid'], 'session_uuid' => ['required', 'uuid'], 'body' => ['required', 'string', 'max:5000']]);
        if (SessionComment::where('uuid', $data['uuid'])->exists()) {
            return ['duplicate', []];
        }
        $session = RidingSession::where('uuid', $data['session_uuid'])->first() ?? throw new SyncRejected('Séance introuvable.');
        $this->horseFor($user, $session->horse_id, Perm::COMMENTS_CREATE);
        SessionComment::create(['uuid' => $data['uuid'], 'riding_session_id' => $session->id, 'author_id' => $user->id, 'body' => $data['body']]);

        return ['applied', []];
    }

    private function dailyLogCreate(User $user, array $p): array
    {
        $data = $this->validate($p, ['uuid' => ['required', 'uuid'], 'horse_id' => ['required', 'integer']] + DailyLogController::rules());
        if (DailyLog::where('uuid', $data['uuid'])->exists()) {
            return ['duplicate', []];
        }
        $horse = $this->horseFor($user, $data['horse_id'], Perm::DAILYLOG_CREATE);
        $data['logged_at'] = isset($data['logged_at']) ? $this->localTime($data['logged_at']) : now();
        DailyLog::create($data + ['author_id' => $user->id, 'horse_id' => $horse->id]);

        return ['applied', []];
    }

    private function observationCreate(User $user, array $p): array
    {
        $data = $this->validate($p, ['uuid' => ['required', 'uuid'], 'horse_id' => ['required', 'integer'], 'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(array_keys(HealthObservation::SEVERITIES))], 'observed_at' => ['nullable', 'date']]);
        if (HealthObservation::where('uuid', $data['uuid'])->exists()) {
            return ['duplicate', []];
        }
        $horse = $this->horseFor($user, $data['horse_id'], Perm::COMMENTS_CREATE);
        HealthObservation::create(['uuid' => $data['uuid'], 'horse_id' => $horse->id, 'author_id' => $user->id, 'body' => $data['body'], 'severity' => $data['severity'], 'observed_at' => isset($data['observed_at']) ? $this->localTime($data['observed_at']) : now()]);

        return ['applied', []];
    }

    private function horseUpdate(User $user, array $p, string $opUuid): array
    {
        $horse = $this->horseFor($user, $p['horse_id'] ?? null, Perm::HORSE_EDIT);
        $changes = array_intersect_key((array) ($p['changes'] ?? []), array_flip(Horse::OFFLINE_EDITABLE));
        $this->validate($changes, array_intersect_key(HorseService::rules(), $changes));

        return $this->threeWayMerge($user, $horse, 'horse', $changes, (array) ($p['base'] ?? []), $opUuid);
    }

    /**
     * Applique les champs dont la valeur serveur est encore égale à la base connue
     * du client ; les autres donnent lieu à un conflit enregistré.
     */
    private function threeWayMerge(User $user, $model, string $entity, array $changes, array $base, string $opUuid): array
    {
        $apply = [];
        $conflicting = [];
        foreach ($changes as $field => $value) {
            if ($field === 'status_changed_at') {
                continue;
            }
            $server = $this->normalize($model->getAttribute($field));
            $local = $this->normalize($value);
            if ($server === $local) {
                continue; // déjà à jour
            }
            if (! array_key_exists($field, $base) || $this->normalize($base[$field]) === $server) {
                $apply[$field] = $value;
            } else {
                $conflicting[$field] = $value;
            }
        }
        if (isset($changes['status_changed_at']) && isset($apply['status'])) {
            $apply['status_changed_at'] = $changes['status_changed_at'];
        }
        if ($apply) {
            $model->update($apply);
        }
        if ($conflicting) {
            $conflict = $this->recordConflict($user, $opUuid, $entity, $model, $conflicting, $model->only(array_keys($conflicting)), (int) ($base['version'] ?? 0));

            return ['conflict', ['message' => 'Modification concurrente détectée : choix requis.', 'conflict_id' => $conflict->id, 'applied_fields' => array_keys($apply)]];
        }

        return [$apply ? 'applied' : 'duplicate', ['version' => $model->version ?? null]];
    }

    /** Les dates client (ISO 8601 avec fuseau) sont converties dans le fuseau de l'application. */
    private function localTime(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    private function normalize($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
            return rescue(fn () => Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i'), $value, false);
        }

        return trim((string) $value);
    }

    private function recordConflict(User $user, string $opUuid, string $entity, $model, array $local, array $server, int $baseVersion): SyncConflict
    {
        return SyncConflict::create([
            'user_id' => $user->id, 'op_uuid' => $opUuid, 'entity' => $entity, 'entity_id' => $model->id, 'entity_uuid' => $model->uuid ?? null,
            'local_values' => $local, 'server_values' => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : $v, $server),
            'base_version' => $baseVersion ?: null, 'server_version' => $model->version ?? null,
        ]);
    }

    /** Résolution explicite d'un conflit par l'utilisateur. */
    public function resolve(User $user, SyncConflict $conflict, string $choice): void
    {
        if ($conflict->status !== 'open') {
            return;
        }
        if ($choice === 'local') {
            $model = match ($conflict->entity) {
                'session' => RidingSession::find($conflict->entity_id),
                'session_exercise' => SessionExercise::find($conflict->entity_id),
                'horse' => Horse::find($conflict->entity_id),
                default => null,
            } ?? throw new SyncRejected('L\'élément n\'existe plus.');

            // Les droits actuels s'appliquent toujours.
            match ($conflict->entity) {
                'session' => $this->sessionByUuid($user, $model->uuid),
                'session_exercise' => $this->sessionByUuid($user, $model->session->uuid),
                'horse' => $this->horseFor($user, $model->id, Perm::HORSE_EDIT),
            };
            if ($conflict->entity === 'session' && $model->isCompleted()) {
                $model->update(array_intersect_key($conflict->local_values, array_flip([...RidingSession::DEBRIEF_FIELDS, 'notes'])));
            } else {
                $model->update($conflict->local_values);
            }
        }
        $conflict->update(['status' => $choice === 'local' ? 'resolved_local' : 'resolved_server', 'resolved_at' => now()]);
    }
}
