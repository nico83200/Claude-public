<?php

namespace App\Services\Assistant;

use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\ObservationController;
use App\Models\CalendarEvent;
use App\Models\CareCategory;
use App\Models\CareRecord;
use App\Models\CareReminderRule;
use App\Models\DailyLog;
use App\Models\Exercise;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeedingEntry;
use App\Models\FeedingPlan;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\HorseMeasurement;
use App\Models\Professional;
use App\Models\RidingSession;
use App\Services\CalendarService;
use App\Services\CareReminders;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use App\Services\RidingSessionService;
use App\Support\Perm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Outils mis à disposition de Claude. Chaque outil applique les mêmes contrôles
 * d'accès que les écrans (droits sur le cheval, offre en lecture seule…) et
 * enregistre de quoi annuler l'action.
 */
class AssistantTools
{
    public const CARE_KEYS = ['veterinary', 'farrier', 'osteopathy', 'dentistry', 'vaccination', 'deworming', 'exams', 'imaging', 'injury', 'treatment', 'routine', 'specialist'];

    public const EXPENSE_KEYS = ['boarding', 'feed', 'veterinary', 'farrier', 'osteopathy', 'dentistry', 'tack', 'equipment', 'competition', 'transport', 'insurance', 'other'];

    public const APPOINTMENT_TYPES = ['veterinary', 'farrier', 'care', 'lesson', 'competition', 'ride', 'rest', 'custom'];

    public function __construct(
        private HorseAccess $access,
        private CalendarService $calendar,
        private RidingSessionService $sessions,
        private CareReminders $reminders,
        private Entitlements $entitlements,
    ) {}

    // ------------------------------------------------------------------ Définitions

    public function definitions(): array
    {
        $str = ['type' => 'string'];
        $int = ['type' => 'integer'];
        $num = ['type' => 'number'];
        $bool = ['type' => 'boolean'];
        // Champ facultatif : simplement non requis (pas d'union « valeur ou null », que l'API limite).
        $n = fn (array $schema) => $schema + ['x-optional' => true];
        $enum = fn (array $values) => ['type' => 'string', 'enum' => array_values($values)];
        $tool = fn (string $name, string $description, array $props) => [
            'name' => $name, 'description' => $description,
            'inputSchema' => self::finalizeSchema(['type' => 'object', 'properties' => $props, 'additionalProperties' => false]),
        ];
        $phases = array_keys(RidingSession::PHASES);

        return [
            $tool('create_appointment', 'Ajoute un rendez-vous ou événement au calendrier d\'un cheval (maréchal, vétérinaire, ostéopathe, dentiste, concours, balade, repos…). Pour une séance de travail monté ou à pied et pour un cours (particulier ou collectif), utiliser create_session.', [
                'horse_id' => $int,
                'type' => $enum(self::APPOINTMENT_TYPES),
                'title' => ['type' => 'string', 'description' => 'Titre court, ex. « Maréchal », « Vaccin grippe », « Ostéopathe ».'],
                'starts_at' => ['type' => 'string', 'description' => 'Date et heure locales au format YYYY-MM-DD HH:MM (00:00 si toute la journée).'],
                'all_day' => $n($bool),
                'duration_minutes' => $n($int),
                'professional_id' => $n($int),
                'notes' => $n($str),
            ]),
            $tool('update_appointment', 'Modifie, déplace, confirme, annule ou marque réalisé un rendez-vous existant du calendrier (identifiant obtenu par find_records). Ne pas utiliser pour une séance.', [
                'event_id' => $int,
                'starts_at' => $n($str),
                'duration_minutes' => $n($int),
                'status' => $n($enum(['planned', 'confirmed', 'done', 'cancelled'])),
                'title' => $n($str),
                'notes' => $n($str),
            ]),
            $tool('create_session', 'Crée une séance de travail (montée, à pied, longe…) ou un cours (session_type private_lesson / group_lesson) avec ses exercices répartis par phase. Choisir d\'abord dans la bibliothèque (exercise_id) ; un exercice libre (exercise_id null + name) seulement si rien ne convient. Pour un cours, exercises peut rester vide : le cavalier les ajoutera au bilan.', [
                'horse_id' => $int,
                'scheduled_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM'],
                'session_type' => $enum(array_keys(RidingSession::TYPES)),
                'discipline' => $n($enum(array_keys(Horse::DISCIPLINES))),
                'objective' => $n($str),
                'planned_minutes' => $int,
                'location' => $n($str),
                'instructor' => $n(['type' => 'string', 'description' => 'Enseignant du cours (prénom ou nom), si mentionné.']),
                'exercises' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
                    'properties' => ['exercise_id' => $n($int), 'name' => $n($str), 'phase' => $enum($phases), 'minutes' => $n($int), 'repetitions' => $n($int), 'instructions' => $n($str)]]],
            ]),
            $tool('update_session', 'Déplace, modifie ou annule une séance prévue (identifiant obtenu par find_records). Une séance terminée ne se modifie pas.', [
                'session_id' => $int,
                'scheduled_at' => $n($str),
                'status' => $n($enum(['planned', 'cancelled'])),
                'objective' => $n($str),
                'planned_minutes' => $n($int),
            ]),
            $tool('record_care', 'Enregistre un soin ou une intervention RÉALISÉ(E) (passage du maréchal, vaccin fait, visite vétérinaire, vermifuge donné…), avec le prochain passage. Pour un rendez-vous futur, utiliser create_appointment.', [
                'horse_id' => $int,
                'category' => $enum(self::CARE_KEYS),
                'performed_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM'],
                'professional_id' => $n($int),
                'reason' => $n($str),
                'care_performed' => $n($str),
                'cost' => $n($num),
                'add_expense' => $n(['type' => 'boolean', 'description' => 'Ajouter le coût aux dépenses (si un coût est donné).']),
                'next_interval_value' => $n($int),
                'next_interval_unit' => $n($enum(['weeks', 'months'])),
                'next_check_on' => $n(['type' => 'string', 'description' => 'YYYY-MM-DD, si une date précise est donnée.']),
                'remember_frequency' => $n(['type' => 'boolean', 'description' => 'Vrai si l\'utilisateur donne une fréquence à retenir pour ce cheval.']),
            ]),
            $tool('add_observation', 'Note une observation de santé ou de comportement (boiterie, gonflement, toux, coup de pied…).', [
                'horse_id' => $int,
                'body' => $str,
                'severity' => $enum(array_keys(HealthObservation::SEVERITIES)),
            ]),
            $tool('add_daily_log', 'Ajoute une entrée au suivi quotidien (appétit, état général, comportement, activité du jour).', [
                'horse_id' => $int,
                'logged_at' => $n($str),
                'appetite' => $n($enum(array_keys(DailyLog::APPETITE))),
                'general_state' => $n($enum(array_keys(DailyLog::STATE))),
                'behavior' => $n($str),
                'activity' => $n($str),
                'observations' => $n($str),
                'anomalies' => $n($str),
            ]),
            $tool('record_weight', 'Enregistre le poids du cheval (pesée, ou estimation au ruban : tour de poitrine et longueur en cm) et/ou sa note d\'état corporel de 1 (très maigre) à 9 (obèse).', [
                'horse_id' => $int,
                'measured_on' => $n(['type' => 'string', 'description' => 'YYYY-MM-DD, aujourd\'hui par défaut']),
                'weight_kg' => $n($num),
                'girth_cm' => $n($int),
                'length_cm' => $n($int),
                'body_condition' => $n($int),
                'notes' => $n($str),
            ]),
            $tool('add_expense', 'Enregistre une dépense (maréchal, pension, aliment, vétérinaire, matériel…) pour un cheval : toute dépense se rapporte à un cheval ; s\'il n\'est pas clair, demande lequel avec ask_user.', [
                'horse_id' => $int,
                'category' => $enum(self::EXPENSE_KEYS),
                'amount' => $num,
                'spent_on' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'supplier' => $n($str),
                'comment' => $n($str),
                'professional_id' => $n($int),
            ]),
            $tool('set_feeding', 'Modifie la ration d\'un cheval : ajoute, modifie ou retire des aliments/compléments (une nouvelle version de la ration démarre aujourd\'hui, l\'historique est conservé).', [
                'horse_id' => $int,
                'changes' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
                    'properties' => ['feed' => $str, 'quantity' => $n($num), 'unit' => $n($str), 'time_of_day' => $n(['type' => 'string', 'description' => 'HH:MM']), 'frequency' => $n($str), 'is_supplement' => $n($bool), 'note' => $n($str), 'remove' => $n($bool)]]],
            ]),
            $tool('find_records', 'Recherche des informations pour répondre à une question ou retrouver un élément à modifier. Pour un total ou une moyenne, utiliser les synthèses : expense_totals (dépenses totales par catégorie, défaut : année en cours), last_care (dernier soin de chaque type et prochaine échéance), weights (pesées et note d\'état), treatments (traitements en cours et récents), session_stats (nombre de séances, minutes, exercices les plus travaillés).', [
                'kind' => $enum(['appointments', 'sessions', 'care', 'expenses', 'observations', 'daily_logs', 'feeding', 'horse_profile', 'expense_totals', 'last_care', 'weights', 'treatments', 'session_stats']),
                'horse_id' => $n($int),
                'from' => $n(['type' => 'string', 'description' => 'YYYY-MM-DD']),
                'to' => $n(['type' => 'string', 'description' => 'YYYY-MM-DD']),
                'limit' => $n($int),
            ]),
            $tool('ask_user', 'Pose une question courte quand une information indispensable manque (ex. quel cheval). Proposer des réponses possibles. Termine le tour.', [
                'question' => $str,
                'options' => ['type' => 'array', 'items' => $str],
            ]),
        ];
    }

    /** Champs requis = champs non marqués facultatifs (récursif pour les listes d'objets). */
    private static function finalizeSchema(array $schema): array
    {
        if (isset($schema['properties'])) {
            $required = [];
            foreach ($schema['properties'] as $key => $prop) {
                if (empty($prop['x-optional'])) {
                    $required[] = $key;
                }
                unset($prop['x-optional']);
                $schema['properties'][$key] = self::finalizeSchema($prop);
            }
            $schema['required'] = $required;
        }
        if (isset($schema['items'])) {
            $schema['items'] = self::finalizeSchema($schema['items']);
        }

        return $schema;
    }

    /** Complète les champs facultatifs absents (null), y compris dans les listes d'objets. */
    private static function withDefaults(array $schema, array $input): array
    {
        foreach ($schema['properties'] ?? [] as $key => $prop) {
            if (! array_key_exists($key, $input)) {
                $input[$key] = ($prop['type'] ?? null) === 'array' ? [] : (($prop['type'] ?? null) === 'boolean' ? false : null);
            } elseif (($prop['type'] ?? null) === 'array' && isset($prop['items']['properties']) && is_array($input[$key])) {
                $input[$key] = array_map(fn ($item) => is_array($item) ? self::withDefaults($prop['items'], $item) : $item, $input[$key]);
            }
        }

        return $input;
    }

    // ------------------------------------------------------------------ Exécution

    /** @return array{content: string, is_error: bool, stop?: bool} */
    public function execute(string $name, array $input, AssistantContext $ctx): array
    {
        $schema = collect($this->definitions())->firstWhere('name', $name)['inputSchema'] ?? null;
        if ($schema) {
            $input = self::withDefaults($schema, $input);
            foreach ($schema['required'] as $key) {
                if ($input[$key] === null || $input[$key] === '') {
                    return ['content' => 'Champ obligatoire manquant : '.$key.'.', 'is_error' => true];
                }
            }
        }
        try {
            $result = match ($name) {
                'create_appointment' => $this->createAppointment($input, $ctx),
                'update_appointment' => $this->updateAppointment($input, $ctx),
                'create_session' => $this->createSession($input, $ctx),
                'update_session' => $this->updateSession($input, $ctx),
                'record_care' => $this->recordCare($input, $ctx),
                'add_observation' => $this->addObservation($input, $ctx),
                'add_daily_log' => $this->addDailyLog($input, $ctx),
                'record_weight' => $this->recordWeight($input, $ctx),
                'add_expense' => $this->addExpense($input, $ctx),
                'set_feeding' => $this->setFeeding($input, $ctx),
                'find_records' => $this->findRecords($input, $ctx),
                'ask_user' => $this->askUser($input, $ctx),
                default => throw new ToolError('Outil inconnu.'),
            };

            return ['content' => json_encode($result, JSON_UNESCAPED_UNICODE), 'is_error' => false, 'stop' => $name === 'ask_user'];
        } catch (ToolError $e) {
            return ['content' => $e->getMessage(), 'is_error' => true];
        } catch (Throwable $e) {
            report($e);

            return ['content' => 'Erreur technique lors de l\'exécution : action non réalisée.', 'is_error' => true];
        }
    }

    // ------------------------------------------------------------------ Rendez-vous

    private function createAppointment(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::CALENDAR_EDIT);
        $start = $this->dateTime($in['starts_at']);
        $event = new CalendarEvent([
            'horse_id' => $horse->id, 'type' => in_array($in['type'], self::APPOINTMENT_TYPES, true) ? $in['type'] : 'custom',
            'title' => $this->clip($in['title'], 120) ?: 'Rendez-vous', 'starts_at' => $start, 'all_day' => (bool) $in['all_day'],
            'duration_minutes' => max(5, min(1440, (int) ($in['duration_minutes'] ?? 60) ?: 60)),
            'professional_id' => $this->professionalId($ctx, $horse, $in['professional_id'] ?? null), 'notes' => $this->clip($in['notes'] ?? null, 2000), 'status' => 'planned',
        ]);
        $event->organization_id = $horse->organization_id;
        $event->created_by = $ctx->user->id;
        $event->save();
        $summary = $event->title.' – '.$horse->shortName().' – '.$this->human($start, (bool) $in['all_day']);
        $ctx->record('appointment.created', $event, $summary, route('calendar.show', $event), ['delete' => [CalendarEvent::class, $event->id]]);

        return ['ok' => true, 'event_id' => $event->id, 'summary' => $summary];
    }

    private function updateAppointment(array $in, AssistantContext $ctx): array
    {
        $event = CalendarEvent::find($in['event_id']) ?? throw new ToolError('Rendez-vous introuvable.');
        if ($event->isSessionEvent()) {
            throw new ToolError('Cet élément est une séance : utiliser update_session avec session_id='.$event->source_id.'.');
        }
        if ($event->horse) {
            $this->horse($ctx, $event->horse_id, Perm::CALENDAR_EDIT);
        } elseif (! $ctx->user->canInOrg($event->organization_id, Perm::CALENDAR_EDIT)) {
            throw new ToolError('Accès insuffisant pour modifier ce rendez-vous.');
        }
        $changes = array_filter([
            'starts_at' => isset($in['starts_at']) ? $this->dateTime($in['starts_at']) : null,
            'duration_minutes' => isset($in['duration_minutes']) ? max(5, min(1440, (int) $in['duration_minutes'])) : null,
            'status' => $in['status'] ?? null,
            'title' => isset($in['title']) ? $this->clip($in['title'], 120) : null,
            'notes' => isset($in['notes']) ? $this->clip($in['notes'], 2000) : null,
        ], fn ($v) => $v !== null && $v !== '');
        if (! $changes) {
            throw new ToolError('Aucune modification indiquée.');
        }
        $before = $this->snapshot($event, array_keys($changes));
        $event->update($changes);
        $summary = $event->title.' – '.($event->horse?->shortName() ?? 'écurie').' : '.match ($changes['status'] ?? null) {
            'cancelled' => 'annulé', 'done' => 'marqué réalisé', 'confirmed' => 'confirmé', default => 'modifié',
        }.(isset($changes['starts_at']) ? ' ('.$this->human($event->starts_at, $event->all_day).')' : '');
        $ctx->record('appointment.updated', $event, $summary, route('calendar.show', $event), ['restore' => [CalendarEvent::class, $event->id, $before]]);

        return ['ok' => true, 'summary' => $summary];
    }

    // ------------------------------------------------------------------ Séances

    private function createSession(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::SESSIONS_CREATE);
        $at = $this->dateTime($in['scheduled_at']);
        $items = [];
        $library = Exercise::visibleTo($ctx->user)->whereIn('id', collect($in['exercises'] ?? [])->pluck('exercise_id')->filter())->pluck('id')->flip();
        foreach (array_slice($in['exercises'] ?? [], 0, 30) as $ex) {
            $id = $ex['exercise_id'] ?? null;
            if ($id && ! $library->has($id)) {
                $id = null; // exercice inconnu : gardé comme exercice libre s'il a un nom
            }
            if (! $id && ! filled($ex['name'] ?? null)) {
                continue;
            }
            $items[] = array_filter([
                'exercise_id' => $id, 'name' => $id ? null : $this->clip($ex['name'], 150), 'phase' => $ex['phase'] ?? 'main',
                'planned_minutes' => isset($ex['minutes']) ? max(0, min(300, (int) $ex['minutes'])) : null,
                'planned_repetitions' => isset($ex['repetitions']) ? max(0, min(500, (int) $ex['repetitions'])) : null,
                'instructions' => $this->clip($ex['instructions'] ?? null, 2000),
            ], fn ($v) => $v !== null);
        }
        $session = $this->sessions->create($horse, $ctx->user, [
            'scheduled_at' => $at, 'session_type' => array_key_exists($in['session_type'], RidingSession::TYPES) ? $in['session_type'] : 'free',
            'discipline' => array_key_exists((string) ($in['discipline'] ?? ''), Horse::DISCIPLINES) ? $in['discipline'] : null,
            'objective' => $this->clip($in['objective'] ?? null, 255), 'planned_minutes' => max(5, min(600, (int) $in['planned_minutes'])),
            'location' => $this->clip($in['location'] ?? null, 255), 'status' => 'planned',
            'instructor' => $this->clip($in['instructor'] ?? null, 100),
        ], $items);
        $summary = $session->title().' – '.$horse->shortName().' – '.$this->human($at)
            .($items || ! $session->isLesson() ? ' · '.count($items).' exercice'.(count($items) > 1 ? 's' : '') : '').' · '.$session->planned_minutes.' min'
            .($session->isCompleted() ? ' · terminée' : '');
        $ctx->record('session.created', $session, $summary, route('sessions.show', $session), ['delete' => [RidingSession::class, $session->id]]);

        return ['ok' => true, 'session_id' => $session->id, 'summary' => $summary, 'exercises' => $session->items()->orderBy('position')->get()->map(fn ($i) => $i->phase.' : '.$i->name.($i->planned_minutes ? ' ('.$i->planned_minutes.' min)' : ''))->all()];
    }

    private function updateSession(array $in, AssistantContext $ctx): array
    {
        $session = RidingSession::find($in['session_id']) ?? throw new ToolError('Séance introuvable.');
        if (! $this->sessions->canEdit($ctx->user, $session) || ! $this->access->can($ctx->user, $session->horse, Perm::SESSIONS_VIEW)) {
            throw new ToolError('Accès insuffisant pour modifier cette séance.');
        }
        if ($session->isCompleted()) {
            throw new ToolError('Séance terminée : elle ne peut plus être modifiée.');
        }
        $changes = array_filter([
            'scheduled_at' => isset($in['scheduled_at']) ? $this->dateTime($in['scheduled_at']) : null,
            'status' => $in['status'] ?? null,
            'objective' => isset($in['objective']) ? $this->clip($in['objective'], 255) : null,
            'planned_minutes' => isset($in['planned_minutes']) ? max(5, min(600, (int) $in['planned_minutes'])) : null,
        ], fn ($v) => $v !== null && $v !== '');
        if (! $changes) {
            throw new ToolError('Aucune modification indiquée.');
        }
        $before = $this->snapshot($session, array_keys($changes));
        $session->update($changes);
        $summary = $session->title().' – '.$session->horse->shortName().' : '.(($changes['status'] ?? null) === 'cancelled' ? 'annulée' : 'modifiée ('.$this->human($session->scheduled_at).')');
        $ctx->record('session.updated', $session, $summary, route('sessions.show', $session), ['restore' => [RidingSession::class, $session->id, $before]]);

        return ['ok' => true, 'summary' => $summary];
    }

    // ------------------------------------------------------------------ Santé et suivi

    private function recordCare(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::HEALTH_EDIT);
        $category = CareCategory::visibleTo($horse->organization_id)->where('key', $in['category'])->first() ?? throw new ToolError('Catégorie de soin inconnue.');
        $performed = $this->dateTime($in['performed_at']);
        if ($performed->isAfter(now()->addHour())) {
            throw new ToolError('La date est dans le futur : pour un rendez-vous à venir, utiliser create_appointment.');
        }
        $next = null;
        if (! empty($in['next_check_on'])) {
            $next = $this->date($in['next_check_on']);
        } elseif (! empty($in['next_interval_value']) && ! empty($in['next_interval_unit'])) {
            $next = CareReminders::addInterval($performed, (int) $in['next_interval_value'], $in['next_interval_unit']);
        } elseif (($rule = $this->reminders->ruleFor($horse, $category->id)) && $rule['enabled']) {
            $next = CareReminders::addInterval($performed, $rule['interval_value'], $rule['interval_unit']);
        }

        return DB::transaction(function () use ($in, $ctx, $horse, $category, $performed, $next) {
            $record = new CareRecord([
                'care_category_id' => $category->id, 'performed_at' => $performed,
                'professional_id' => $this->professionalId($ctx, $horse, $in['professional_id'] ?? null),
                'reason' => $this->clip($in['reason'] ?? null, 255), 'care_performed' => $this->clip($in['care_performed'] ?? null, 10000),
                'cost' => isset($in['cost']) ? max(0, (float) $in['cost']) : null, 'next_check_on' => $next?->toDateString(),
            ]);
            $record->horse_id = $horse->id;
            $record->author_id = $ctx->user->id;
            $record->save();
            $closed = $this->reminders->closePrevious($record, $this->calendar);
            $ruleBefore = null;
            if (! empty($in['remember_frequency']) && ! empty($in['next_interval_value']) && ! empty($in['next_interval_unit'])) {
                $ruleBefore = CareReminderRule::where('horse_id', $horse->id)->where('care_category_id', $category->id)->first()?->only(['enabled', 'interval_value', 'interval_unit', 'notice_days']) ?? false;
                $this->reminders->save($horse, $category->id, ['enabled' => true, 'interval_value' => (int) $in['next_interval_value'], 'interval_unit' => $in['next_interval_unit'], 'notice_days' => $this->reminders->ruleFor($horse, $category->id)['notice_days'] ?? 7]);
            }
            $expenseId = null;
            if (! empty($in['add_expense']) && $record->cost && $this->access->can($ctx->user, $horse, Perm::EXPENSES_CREATE)) {
                $catKey = in_array($category->key, ['veterinary', 'farrier', 'osteopathy', 'dentistry'], true) ? $category->key : 'veterinary';
                $expense = new Expense(['horse_id' => $horse->id, 'expense_category_id' => ExpenseCategory::where('key', $catKey)->value('id'), 'professional_id' => $record->professional_id,
                    'care_record_id' => $record->id, 'spent_on' => $performed->toDateString(), 'amount' => $record->cost, 'supplier' => $record->professional?->fullName(), 'comment' => $record->reason]);
                $expense->organization_id = $horse->organization_id;
                $expense->author_id = $ctx->user->id;
                $expense->save();
                $expenseId = $expense->id;
            }
            $summary = $category->name.' – '.$horse->shortName().' – '.$performed->translatedFormat('j M Y').($next ? ' · prochain le '.$next->translatedFormat('j M Y') : '').($expenseId ? ' · '.number_format((float) $record->cost, 2, ',', ' ').' € en dépense' : '');
            $ctx->record('care.created', $record, $summary, route('horses.care.show', [$horse, $record]), [
                'delete' => [CareRecord::class, $record->id], 'expense' => $expenseId, 'reopen' => $closed,
                'rule' => $ruleBefore === null ? null : ['horse_id' => $horse->id, 'category_id' => $category->id, 'before' => $ruleBefore ?: null],
            ]);

            return ['ok' => true, 'summary' => $summary];
        });
    }

    private function addObservation(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::COMMENTS_CREATE);
        $severity = array_key_exists($in['severity'], HealthObservation::SEVERITIES) ? $in['severity'] : 'info';
        $obs = HealthObservation::create(['horse_id' => $horse->id, 'author_id' => $ctx->user->id, 'observed_at' => now(), 'severity' => $severity, 'body' => $this->clip($in['body'], 5000)]);
        if ($severity !== 'info') {
            ObservationController::notifyManagers($horse, $ctx->user, $this->access, 'Observation : '.$horse->shortName(), HealthObservation::SEVERITIES[$severity].' signalée par '.$ctx->user->name.'.');
        }
        $summary = 'Observation ('.mb_strtolower(HealthObservation::SEVERITIES[$severity]).') – '.$horse->shortName().' : '.$this->clip($obs->body, 80);
        $ctx->record('observation.created', $obs, $summary, route('horses.care.index', $horse), ['delete' => [HealthObservation::class, $obs->id]]);

        return ['ok' => true, 'summary' => $summary];
    }

    private function addDailyLog(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::DAILYLOG_CREATE);
        $log = DailyLog::create([
            'horse_id' => $horse->id, 'author_id' => $ctx->user->id, 'logged_at' => ! empty($in['logged_at']) ? $this->dateTime($in['logged_at']) : now(),
            'appetite' => array_key_exists((string) ($in['appetite'] ?? ''), DailyLog::APPETITE) ? $in['appetite'] : null,
            'general_state' => array_key_exists((string) ($in['general_state'] ?? ''), DailyLog::STATE) ? $in['general_state'] : null,
            'behavior' => $this->clip($in['behavior'] ?? null, 255), 'activity' => $this->clip($in['activity'] ?? null, 255),
            'observations' => $this->clip($in['observations'] ?? null, 5000), 'anomalies' => $this->clip($in['anomalies'] ?? null, 5000),
        ]);
        if ($log->anomalies || $log->general_state === 'poor' || $log->appetite === 'none') {
            ObservationController::notifyManagers($horse, $ctx->user, $this->access, 'Suivi quotidien : '.$horse->shortName(), 'Un point d\'attention a été noté dans le suivi quotidien par '.$ctx->user->name.'.');
        }
        $summary = 'Suivi quotidien – '.$horse->shortName().' – '.$log->logged_at->translatedFormat('j M');
        $ctx->record('dailylog.created', $log, $summary, route('horses.logs.index', $horse), ['delete' => [DailyLog::class, $log->id]]);

        return ['ok' => true, 'summary' => $summary];
    }

    private function recordWeight(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::DAILYLOG_CREATE);
        $tape = ! empty($in['girth_cm']) && ! empty($in['length_cm']) && empty($in['weight_kg']);
        $weight = $tape ? HorseMeasurement::estimateFromTape((int) $in['girth_cm'], (int) $in['length_cm']) : (isset($in['weight_kg']) ? (float) $in['weight_kg'] : null);
        $condition = isset($in['body_condition']) ? (int) $in['body_condition'] : null;
        if ($weight === null && $condition === null) {
            throw new ToolError('Indiquez le poids, les mesures au ruban ou la note d\'état corporel.');
        }
        if (($weight !== null && ($weight < 30 || $weight > 1500)) || ($condition !== null && ($condition < 1 || $condition > 9))) {
            throw new ToolError('Valeur hors limites (poids 30 à 1 500 kg, note 1 à 9).');
        }
        $on = ! empty($in['measured_on']) ? $this->date($in['measured_on']) : today();
        if ($on->isFuture()) {
            throw new ToolError('La date de mesure ne peut pas être dans le futur.');
        }
        $previous = $horse->measurements()->where('measured_on', '<=', $on->toDateString())->get()->last();
        $m = HorseMeasurement::create([
            'horse_id' => $horse->id, 'author_id' => $ctx->user->id, 'measured_on' => $on->toDateString(), 'weight_kg' => $weight,
            'method' => $tape ? 'tape' : 'scale', 'girth_cm' => $tape ? (int) $in['girth_cm'] : null, 'length_cm' => $tape ? (int) $in['length_cm'] : null,
            'body_condition' => $condition, 'notes' => $this->clip($in['notes'] ?? null, 500),
        ]);
        $alerts = MeasurementController::alerts($previous, $m);
        if ($alerts) {
            ObservationController::notifyManagers($horse, $ctx->user, $this->access, 'Variation de poids : '.$horse->shortName(), implode(' ', $alerts), route('horses.weight', $horse));
        }
        $summary = 'Poids – '.$horse->shortName().' – '.($weight ? number_format($weight, 0, ',', ' ').' kg'.($tape ? ' (estimé au ruban)' : '') : 'état '.$condition.'/9');
        $ctx->record('measurement.created', $m, $summary, route('horses.weight', $horse), ['delete' => [HorseMeasurement::class, $m->id]]);

        return ['ok' => true, 'summary' => $summary, 'alerts' => $alerts];
    }

    private function addExpense(array $in, AssistantContext $ctx): array
    {
        if (empty($in['horse_id'])) {
            throw new ToolError('Une dépense se rapporte toujours à un cheval : demandez lequel.');
        }
        $horse = $this->horse($ctx, $in['horse_id'], Perm::EXPENSES_CREATE);
        $org = $horse->organization;
        if (! $this->entitlements->hasFeature($org, 'budget')) {
            throw new ToolError('Le suivi des dépenses n\'est pas inclus dans l\'offre de cet espace.');
        }
        if (! $this->entitlements->writable($org)) {
            throw new ToolError('Espace en lecture seule : abonnement à régulariser.');
        }
        $amount = round((float) $in['amount'], 2);
        if ($amount <= 0 || $amount > 1000000) {
            throw new ToolError('Montant invalide.');
        }
        $expense = new Expense([
            'horse_id' => $horse?->id, 'expense_category_id' => ExpenseCategory::where('key', $in['category'])->value('id') ?? ExpenseCategory::where('key', 'other')->value('id'),
            'professional_id' => $horse ? $this->professionalId($ctx, $horse, $in['professional_id'] ?? null) : null,
            'spent_on' => $this->date($in['spent_on'])->toDateString(), 'amount' => $amount,
            'supplier' => $this->clip($in['supplier'] ?? null, 255), 'comment' => $this->clip($in['comment'] ?? null, 2000),
        ]);
        $expense->organization_id = $org->id;
        $expense->author_id = $ctx->user->id;
        $expense->save();
        $summary = 'Dépense '.number_format($amount, 2, ',', ' ').' € – '.($expense->category?->name ?? 'Autre').($horse ? ' – '.$horse->shortName() : '').' – '.$expense->spent_on->translatedFormat('j M Y');
        $ctx->record('expense.created', $expense, $summary, route('budget.index'), ['delete' => [Expense::class, $expense->id]]);
        // Dépense de soin : le soin est ajouté au carnet de santé (ou rattaché au soin du même jour déjà noté).
        if ($care = app(\App\Services\CareExpenseSync::class)->careFromExpense($expense, $ctx->user)) {
            if ($care->wasRecentlyCreated) {
                $ctx->record('care.created', $care, 'Soin ajouté au carnet de santé – '.$care->category->name.' – '.$horse->shortName(), route('horses.care.show', [$horse, $care]), ['delete' => [CareRecord::class, $care->id]]);
            }
            $summary .= ' (soin ajouté au carnet de santé)';
        }

        return ['ok' => true, 'summary' => $summary];
    }

    private function setFeeding(array $in, AssistantContext $ctx): array
    {
        $horse = $this->horse($ctx, $in['horse_id'], Perm::FEEDING_EDIT);
        $changes = array_slice($in['changes'] ?? [], 0, 20);
        if (! $changes) {
            throw new ToolError('Aucun changement de ration indiqué.');
        }

        return DB::transaction(function () use ($horse, $changes, $ctx) {
            $today = now()->startOfDay();
            $current = FeedingPlan::with('entries')->where('horse_id', $horse->id)->where('starts_on', '<=', $today->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString()))->latest('starts_on')->first();
            if ($current && $current->starts_on->isSameDay($today)) {
                $plan = $current;
                $undo = ['feeding_snapshot' => ['plan_id' => $plan->id, 'entries' => $plan->entries->map->only(['feed', 'quantity', 'unit', 'time_of_day', 'frequency', 'is_supplement', 'note'])->values()->all()]];
            } else {
                // Nouvelle version de la ration : l'ancienne s'arrête hier (historique conservé).
                $plan = FeedingPlan::create(['horse_id' => $horse->id, 'author_id' => $ctx->user->id, 'name' => $current?->name ?? 'Ration', 'instructions' => $current?->instructions, 'starts_on' => $today->toDateString()]);
                foreach ($current?->entries ?? [] as $e) {
                    $plan->entries()->create($e->only(['feed', 'quantity', 'unit', 'time_of_day', 'frequency', 'is_supplement', 'note']));
                }
                $undo = ['feeding_new' => ['plan_id' => $plan->id, 'previous_id' => $current?->id, 'previous_ends_on' => $current?->ends_on?->toDateString()]];
                $current?->update(['ends_on' => $today->copy()->subDay()->toDateString()]);
            }
            $done = [];
            foreach ($changes as $c) {
                $feed = $this->clip($c['feed'], 120);
                $time = ! empty($c['time_of_day']) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $c['time_of_day']) ? $c['time_of_day'].':00' : null;
                $match = $plan->entries()->get()->first(fn (FeedingEntry $e) => mb_strtolower(trim($e->feed)) === mb_strtolower(trim($feed)) && (! $time || ! $e->time_of_day || substr((string) $e->time_of_day, 0, 5) === substr($time, 0, 5)));
                if (! empty($c['remove'])) {
                    if ($match) {
                        $match->delete();
                        $done[] = 'retiré : '.$feed;
                    }

                    continue;
                }
                $values = array_filter(['feed' => $feed, 'quantity' => isset($c['quantity']) ? max(0, (float) $c['quantity']) : null, 'unit' => $this->clip($c['unit'] ?? null, 20),
                    'time_of_day' => $time, 'frequency' => $this->clip($c['frequency'] ?? null, 60), 'note' => $this->clip($c['note'] ?? null, 255)], fn ($v) => $v !== null && $v !== '');
                $values['is_supplement'] = (bool) ($c['is_supplement'] ?? false);
                if ($match) {
                    unset($values['feed']); // garde le libellé existant
                    $match->update($values);
                    $done[] = 'modifié : '.$feed.(isset($values['quantity']) ? ' '.rtrim(rtrim(number_format($values['quantity'], 2, ',', ''), '0'), ',').' '.($values['unit'] ?? $match->unit) : '');
                } else {
                    $plan->entries()->create($values + ['unit' => 'kg']);
                    $done[] = 'ajouté : '.$feed;
                }
            }
            $summary = 'Ration – '.$horse->shortName().' : '.implode(', ', $done ?: ['aucun changement']);
            $ctx->record('feeding.updated', $plan, $summary, route('horses.feeding', $horse), $undo);

            return ['ok' => true, 'summary' => $summary];
        });
    }

    // ------------------------------------------------------------------ Lecture

    private function findRecords(array $in, AssistantContext $ctx): array
    {
        $limit = max(1, min(30, (int) ($in['limit'] ?? 10) ?: 10));
        $from = ! empty($in['from']) ? $this->date($in['from'])->startOfDay() : null;
        $to = ! empty($in['to']) ? $this->date($in['to'])->endOfDay() : null;
        $ids = fn (string $perm) => ! empty($in['horse_id'])
            ? ($this->access->can($ctx->user, Horse::find($in['horse_id']) ?? new Horse, $perm) ? collect([(int) $in['horse_id']]) : collect())
            : $this->access->horseIdsWith($ctx->user, $perm);
        $range = fn ($q, string $col) => $q->when($from, fn ($w) => $w->where($col, '>=', $from))->when($to, fn ($w) => $w->where($col, '<=', $to));
        $name = fn ($m) => $m->horse?->shortName();

        return match ($in['kind']) {
            'appointments' => CalendarEvent::with('horse', 'professional')->whereIn('horse_id', $ids(Perm::CALENDAR_VIEW))->visibleSessions($ids(Perm::SESSIONS_VIEW))
                ->tap(fn ($q) => $range($q, 'starts_at'))->when(! $from && ! $to, fn ($q) => $q->where('starts_at', '>=', now()->startOfDay()))
                ->orderBy('starts_at')->limit($limit)->get()
                ->map(fn ($e) => ['event_id' => $e->id, 'session_id' => $e->isSessionEvent() ? $e->source_id : null, 'horse' => $name($e), 'type' => $e->type, 'title' => $e->title, 'starts_at' => $e->starts_at->format('Y-m-d H:i'), 'status' => $e->status, 'professional' => $e->professional?->fullName()])->all(),
            'sessions' => RidingSession::with('horse', 'items')->whereIn('horse_id', $ids(Perm::SESSIONS_VIEW))->tap(fn ($q) => $range($q, 'scheduled_at'))
                ->orderByDesc('scheduled_at')->limit($limit)->get()
                ->map(fn ($s) => ['session_id' => $s->id, 'horse' => $name($s), 'date' => $s->scheduled_at->format('Y-m-d H:i'), 'status' => $s->status, 'type' => $s->session_type, 'objective' => $s->objective,
                    'minutes' => $s->actual_minutes ?: $s->planned_minutes, 'exercises' => $s->items->pluck('name')->take(12)->all(), 'progress' => $s->progress, 'to_rework' => $s->to_rework])->all(),
            'care' => CareRecord::with('horse', 'category', 'professional')->whereIn('horse_id', $ids(Perm::HEALTH_VIEW))->tap(fn ($q) => $range($q, 'performed_at'))
                ->orderByDesc('performed_at')->limit($limit)->get()
                ->map(fn ($c) => ['horse' => $name($c), 'category' => $c->category?->key, 'date' => $c->performed_at->format('Y-m-d'), 'professional' => $c->professional?->fullName(), 'reason' => $c->reason, 'next_check_on' => $c->next_check_done_at ? null : $c->next_check_on?->toDateString()])->all(),
            'expenses' => Expense::with('horse', 'category')->whereIn('horse_id', $ids(Perm::EXPENSES_VIEW))->tap(fn ($q) => $range($q, 'spent_on'))
                ->orderByDesc('spent_on')->limit($limit)->get()
                ->map(fn ($x) => ['horse' => $name($x), 'date' => $x->spent_on->toDateString(), 'category' => $x->category?->name, 'amount' => (float) $x->amount, 'supplier' => $x->supplier])->all(),
            'observations' => HealthObservation::with('horse')->whereIn('horse_id', $ids(Perm::HEALTH_VIEW))->tap(fn ($q) => $range($q, 'observed_at'))
                ->orderByDesc('observed_at')->limit($limit)->get()
                ->map(fn ($o) => ['horse' => $name($o), 'date' => $o->observed_at->format('Y-m-d'), 'severity' => $o->severity, 'body' => $this->clip($o->body, 300), 'resolved' => (bool) $o->resolved_at])->all(),
            'daily_logs' => DailyLog::with('horse')->whereIn('horse_id', $ids(Perm::HORSE_VIEW))->tap(fn ($q) => $range($q, 'logged_at'))
                ->orderByDesc('logged_at')->limit($limit)->get()
                ->map(fn ($l) => ['horse' => $name($l), 'date' => $l->logged_at->format('Y-m-d H:i'), 'appetite' => $l->appetite, 'state' => $l->general_state, 'activity' => $l->activity, 'anomalies' => $l->anomalies])->all(),
            'feeding' => FeedingPlan::with('horse', 'entries')->whereIn('horse_id', $ids(Perm::FEEDING_VIEW))->where('starts_on', '<=', now()->toDateString())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))->get()
                ->map(fn ($p) => ['horse' => $name($p), 'since' => $p->starts_on->toDateString(), 'entries' => $p->entries->map(fn ($e) => trim($e->feed.' '.($e->quantity !== null ? (float) $e->quantity.' '.$e->unit : '').' '.($e->time_of_day ? substr((string) $e->time_of_day, 0, 5) : '').' '.$e->frequency))->all()])->all(),
            'horse_profile' => Horse::with('breed')->whereIn('id', $ids(Perm::HORSE_VIEW))->limit($limit)->get()->map(fn ($h) => [
                'horse_id' => $h->id, 'name' => $h->shortName(), 'official_name' => $h->official_name, 'breed' => $h->breed?->name, 'sex' => $h->sex,
                'age' => $h->birth_date ? $h->birth_date->age : ($h->birth_year ? now()->year - $h->birth_year : null), 'discipline' => $h->main_discipline, 'level' => $h->work_level,
                'particularities' => $this->clip($h->particularities, 400), 'precautions' => $this->clip($h->precautions, 400),
                'follow_up' => $this->access->can($ctx->user, $h, Perm::HEALTH_VIEW) ? $this->reminders->rulesFor($h)->filter(fn ($r) => $r['enabled'])->map(fn ($r) => $r['category']->name.' tous les '.CareReminders::describe($r['interval_value'], $r['interval_unit']))->values()->all() : [],
            ])->all(),
            'expense_totals' => $this->expenseTotals($ids(Perm::EXPENSES_VIEW), $from ?? now()->startOfYear(), $to ?? now()->endOfDay()),
            'last_care' => CareRecord::with('horse', 'category', 'professional')->whereIn('horse_id', $ids(Perm::HEALTH_VIEW))->orderByDesc('performed_at')->get()
                ->unique(fn ($c) => $c->horse_id.'-'.$c->care_category_id)->take(40)
                ->map(fn ($c) => ['horse' => $name($c), 'category' => $c->category?->name, 'last' => $c->performed_at->format('Y-m-d'), 'days_ago' => (int) $c->performed_at->diffInDays(now()),
                    'professional' => $c->professional?->fullName(), 'next_check_on' => $c->next_check_done_at ? null : $c->next_check_on?->toDateString()])->values()->all(),
            'weights' => HorseMeasurement::with('horse')->whereIn('horse_id', $ids(Perm::HEALTH_VIEW))->tap(fn ($q) => $range($q, 'measured_on'))
                ->orderByDesc('measured_on')->limit($limit)->get()
                ->map(fn ($m) => ['horse' => $name($m), 'date' => $m->measured_on->toDateString(), 'weight_kg' => $m->weight_kg, 'body_condition' => $m->body_condition])->all(),
            'treatments' => \App\Models\Treatment::with('horse')->whereIn('horse_id', $ids(Perm::TREATMENTS_VIEW))
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('starts_on', '>=', now()->subMonths(6)->toDateString()))->orderByDesc('starts_on')->limit($limit)->get()
                ->map(fn ($t) => ['horse' => $name($t), 'product' => $t->product, 'dosage' => $t->dosage, 'frequency' => $t->frequency, 'from' => $t->starts_on->toDateString(), 'to' => $t->ends_on?->toDateString(), 'status' => $t->status])->all(),
            'session_stats' => $this->sessionStats($ids(Perm::SESSIONS_VIEW), $from ?? now()->subDays(30)->startOfDay(), $to ?? now()->endOfDay()),
            default => throw new ToolError('Type de recherche inconnu.'),
        };
    }

    /** Totaux de dépenses par catégorie (et par cheval) sur une période. */
    private function expenseTotals($horseIds, Carbon $from, Carbon $to): array
    {
        $rows = Expense::with('horse', 'category')->whereIn('horse_id', $horseIds)->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->get();

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'total' => round((float) $rows->sum('amount'), 2), 'count' => $rows->count(),
            'by_category' => $rows->groupBy(fn ($e) => $e->category?->name ?? 'Autre')->map(fn ($g) => ['total' => round((float) $g->sum('amount'), 2), 'count' => $g->count()])->sortByDesc('total')->all(),
            'by_horse' => $rows->groupBy(fn ($e) => $e->horse?->shortName() ?? 'Espace')->map(fn ($g) => round((float) $g->sum('amount'), 2))->sortDesc()->all(),
            'unpaid' => round((float) $rows->whereNull('paid_on')->sum('amount'), 2),
        ];
    }

    /** Nombre de séances, minutes et exercices les plus travaillés sur une période. */
    private function sessionStats($horseIds, Carbon $from, Carbon $to): array
    {
        $sessions = RidingSession::with('horse', 'items')->whereIn('horse_id', $horseIds)->whereBetween('scheduled_at', [$from, $to])->get();
        $done = $sessions->where('status', 'completed');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'completed' => $done->count(), 'planned' => $sessions->where('status', 'planned')->count(),
            'minutes' => (int) $done->sum(fn ($s) => $s->actual_minutes ?: $s->planned_minutes),
            'by_horse' => $done->groupBy(fn ($s) => $s->horse?->shortName())->map->count()->all(),
            'top_exercises' => $done->flatMap(fn ($s) => $s->items->pluck('name'))->countBy()->sortDesc()->take(8)->all(),
            'to_rework' => $done->pluck('to_rework')->filter()->take(5)->values()->all(),
        ];
    }

    private function askUser(array $in, AssistantContext $ctx): array
    {
        $ctx->question = ['question' => $this->clip($in['question'], 300), 'options' => array_values(array_slice(array_filter(array_map(fn ($o) => $this->clip((string) $o, 60), $in['options'] ?? [])), 0, 6))];

        return ['ok' => true, 'note' => 'Question affichée ; attendre la réponse de l\'utilisateur.'];
    }

    // ------------------------------------------------------------------ Outils internes

    private function horse(AssistantContext $ctx, $id, string $perm): Horse
    {
        $horse = Horse::with('organization')->find((int) $id);
        if (! $horse || ! in_array(Perm::HORSE_VIEW, $this->access->permissions($ctx->user, $horse), true)) {
            throw new ToolError('Cheval introuvable ou non accessible.');
        }
        if (! $this->access->can($ctx->user, $horse, $perm)) {
            throw new ToolError($this->access->denialReason($ctx->user, $horse, $perm));
        }

        return $horse;
    }

    private function professionalId(AssistantContext $ctx, Horse $horse, $id): ?int
    {
        return $id ? Professional::where('organization_id', $horse->organization_id)->whereKey((int) $id)->value('id') : null;
    }

    private function dateTime(string $value): Carbon
    {
        try {
            return Carbon::parse(trim($value), config('app.timezone'));
        } catch (Throwable) {
            throw new ToolError('Date invalide : '.$value.' (format attendu YYYY-MM-DD HH:MM).');
        }
    }

    private function date(string $value): Carbon
    {
        return $this->dateTime($value)->startOfDay();
    }

    private function human(Carbon $at, bool $allDay = false): string
    {
        $day = $at->isToday() ? 'aujourd\'hui' : ($at->isTomorrow() ? 'demain' : $at->translatedFormat('D j M Y'));

        return $allDay ? $day : $day.' à '.$at->format($at->minute ? 'G \h i' : 'G \h');
    }

    private function clip(?string $value, int $max): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' || $value === null ? null : mb_substr($value, 0, $max);
    }

    private function snapshot($model, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn ($k) => [$k => $model->getRawOriginal($k)])->all() + ['__updated_at' => $model->getRawOriginal('updated_at')];
    }
}
