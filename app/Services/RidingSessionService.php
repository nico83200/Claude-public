<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Horse;
use App\Models\RidingSession;
use App\Models\SessionExercise;
use App\Models\SessionTemplate;
use App\Models\User;
use App\Support\Perm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Règles métier des séances, partagées par l'interface web et la synchronisation.
 */
class RidingSessionService
{
    public function __construct(private HorseAccess $access) {}

    public static function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'rider_id' => ['nullable', 'integer'],
            'rider_name' => ['nullable', 'string', 'max:100'],
            'discipline' => ['nullable', Rule::in(array_keys(Horse::DISCIPLINES))],
            'session_type' => ['required', Rule::in(array_keys(RidingSession::TYPES))],
            'objective' => ['nullable', 'string', 'max:255'],
            'planned_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'location' => ['nullable', 'string', 'max:150'],
            'horse_state_before' => ['nullable', 'string', 'max:255'],
            'precautions' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public static function debriefRules(): array
    {
        return [
            'actual_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'rider_feeling' => ['nullable', 'integer', 'between:1,5'],
            'horse_behavior' => ['nullable', 'integer', 'between:1,5'],
            'concentration' => ['nullable', 'integer', 'between:1,5'],
            'availability' => ['nullable', 'integer', 'between:1,5'],
            'difficulties' => ['nullable', 'string', 'max:5000'],
            'progress' => ['nullable', 'string', 'max:5000'],
            'to_rework' => ['nullable', 'string', 'max:5000'],
            'anomalies' => ['nullable', 'string', 'max:5000'],
            'next_objectives' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function canEdit(User $user, RidingSession $session): bool
    {
        $horse = $session->horse;
        if ($this->access->can($user, $horse, Perm::SESSIONS_EDIT_ALL)) {
            return true;
        }

        return $this->access->can($user, $horse, Perm::SESSIONS_EDIT_OWN)
            && ($session->created_by === $user->id || $session->rider_id === $user->id);
    }

    /** Le cavalier désigné doit avoir accès au cheval, sinon il est ignoré. */
    public function sanitizeRider(Horse $horse, array $data, User $creator): array
    {
        if (! empty($data['rider_id'])) {
            $rider = User::find($data['rider_id']);
            if (! $rider || ! $this->access->permissions($rider, $horse)) {
                $data['rider_id'] = null;
            }
        }
        if (empty($data['rider_id']) && empty($data['rider_name'])) {
            $data['rider_id'] = $creator->id;
        }

        return $data;
    }

    public function create(Horse $horse, User $user, array $data, array $items = [], ?string $uuid = null): RidingSession
    {
        return DB::transaction(function () use ($horse, $user, $data, $items, $uuid) {
            $session = new RidingSession($this->sanitizeRider($horse, $data, $user));
            $session->horse_id = $horse->id;
            $session->created_by = $user->id;
            if ($uuid) {
                $session->uuid = $uuid;
            }
            $session->save();
            $this->addItems($session, $user, $items);

            return $session;
        });
    }

    /**
     * @param  array<int, array{exercise_id?: int|null, name?: string, phase?: string, planned_minutes?: int|null, planned_repetitions?: int|null, instructions?: string|null, is_break?: bool, uuid?: string}>  $items
     */
    public function addItems(RidingSession $session, User $user, array $items): void
    {
        $position = (int) $session->items()->max('position');
        foreach ($items as $item) {
            $phase = $item['phase'] ?? 'main';
            $phase = in_array($phase, array_keys(RidingSession::PHASES), true) ? $phase : 'main';
            $overrides = [
                'phase' => $phase,
                'planned_minutes' => $item['planned_minutes'] ?? null,
                'planned_repetitions' => $item['planned_repetitions'] ?? null,
                'instructions' => $item['instructions'] ?? null,
            ];
            if (! empty($item['exercise_id'])) {
                $exercise = Exercise::visibleTo($user)->find($item['exercise_id']);
                if (! $exercise) {
                    continue;
                }
                $row = SessionExercise::fromExercise($exercise, $overrides);
            } else {
                $row = new SessionExercise($overrides + [
                    'name' => ! empty($item['is_break']) ? ($item['name'] ?? 'Pause') : ($item['name'] ?? 'Exercice libre'),
                    'is_break' => (bool) ($item['is_break'] ?? false),
                ]);
            }
            if (! empty($item['uuid'])) {
                $row->uuid = $item['uuid'];
            }
            $row->position = ++$position;
            $session->items()->save($row);
        }
    }

    /** Nouvelle séance prévue à partir d'une séance existante : l'originale n'est jamais modifiée. */
    public function duplicate(RidingSession $source, User $user, ?string $scheduledAt = null): RidingSession
    {
        return DB::transaction(function () use ($source, $user, $scheduledAt) {
            $copy = new RidingSession($source->only(['discipline', 'session_type', 'objective', 'planned_minutes', 'location', 'precautions', 'rider_id', 'rider_name']));
            $copy->horse_id = $source->horse_id;
            $copy->created_by = $user->id;
            $copy->scheduled_at = $scheduledAt ?? now()->addDay()->setTime(10, 0);
            $copy->status = 'planned';
            $copy->save();
            foreach ($source->exercises as $item) {
                $copy->items()->create($item->only(['exercise_id', 'exercise_version', 'phase', 'position', 'is_break', 'name', 'snapshot', 'planned_minutes', 'planned_repetitions', 'instructions']));
            }

            return $copy;
        });
    }

    public function fromTemplate(SessionTemplate $template, Horse $horse, User $user, array $data): RidingSession
    {
        $data += ['session_type' => $template->session_type ?? 'free', 'discipline' => $template->discipline, 'objective' => $template->objective, 'planned_minutes' => $template->planned_minutes];
        $session = $this->create($horse, $user, $data, $template->items ?? []);
        $session->update(['template_id' => $template->id]);

        return $session;
    }

    public function complete(RidingSession $session, array $debrief): RidingSession
    {
        $session->fill($debrief);
        $session->status = 'completed';
        $session->completed_at ??= now();
        if (! $session->actual_minutes) {
            $session->actual_minutes = (int) $session->items()->where('status', 'done')->sum('actual_minutes') ?: $session->planned_minutes;
        }
        $session->save();

        return $session;
    }
}
