<?php

namespace App\Services;

use App\Http\Controllers\RidingSessionController;
use App\Models\Exercise;
use App\Models\ExerciseCategory;
use App\Models\RidingSession;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Collection;

/**
 * Exercices proposés au cavalier pour chaque étape de la séance :
 * d'abord ses exercices habituels (séances passées, les plus récentes et réellement faites comptent davantage,
 * avec un bonus pour le même cheval), complétés par des exercices adaptés à son niveau (galop).
 */
class ExerciseSuggestions
{
    private const PER_PHASE = 6;

    /**
     * @return array{rider: ?User, galop: ?int, level: ?string, phases: array<string, array<int, array{id: int, reason: string}>>}
     */
    public function forSession(RidingSession $session, User $viewer, Collection $library, ?int $galopOverride = null): array
    {
        $rider = $session->rider ?? ($session->rider_name ? null : $viewer);

        return $this->compute($rider, $session->horse_id, $session->id, $session->exercises->pluck('exercise_id')->filter()->all(), $library, $galopOverride);
    }

    /** Mêmes suggestions hors séance (page Exercices) : habitudes de l'utilisateur et exercices de son niveau. */
    public function forUser(User $user, Collection $library, ?int $galopOverride = null): array
    {
        return $this->compute($user, null, null, [], $library, $galopOverride);
    }

    private function compute(?User $rider, ?int $horseId, ?int $excludeSessionId, array $already, Collection $library, ?int $galopOverride): array
    {
        $rider?->loadMissing('profile');
        $galop = $galopOverride ?? $rider?->profile?->galop();
        $level = UserProfile::exerciseLevel($galop);
        $visible = $library->keyBy('id');
        $history = $rider ? $this->history($rider, $horseId, $excludeSessionId) : collect();
        $priority = RidingSessionController::phasePriority(ExerciseCategory::orderBy('sort_order')->pluck('name', 'id'));

        $phases = [];
        foreach (array_keys(RidingSession::PHASES) as $phase) {
            $picked = [];
            foreach ($history->get($phase, collect())->sortByDesc('score') as $row) {
                $ex = $visible->get($row['exercise_id']);
                if (! $ex || in_array($ex->id, $already, true) || count($picked) >= self::PER_PHASE) {
                    continue;
                }
                $picked[$ex->id] = ['id' => $ex->id, 'reason' => 'Fait '.$row['count'].' fois'.($row['days'] !== null ? ' · dernière fois '.($row['days'] === 0 ? 'aujourd\'hui' : 'il y a '.$row['days'].' j') : '')];
            }
            // Historique trop court : exercices adaptés au niveau, dans les catégories utiles à l'étape.
            if ($level && count($picked) < 3) {
                $fill = $library->filter(fn (Exercise $e) => in_array($e->level, [$level, 'all'], true) && ! isset($picked[$e->id]) && ! in_array($e->id, $already, true)
                        && ($priority[$phase][$e->exercise_category_id] ?? 999) < 100)
                    ->sortBy(fn (Exercise $e) => [($priority[$phase][$e->exercise_category_id] ?? 999), $e->level === $level ? 0 : 1, $e->name]);
                foreach ($fill as $e) {
                    if (count($picked) >= self::PER_PHASE) {
                        break;
                    }
                    $picked[$e->id] = ['id' => $e->id, 'reason' => 'Adapté au niveau '.['beginner' => 'débutant (galops 1 à 3)', 'intermediate' => 'intermédiaire (galops 4 et 5)', 'advanced' => 'expert (galop 6 et plus)'][$level]];
                }
            }
            $phases[$phase] = array_values($picked);
        }

        return ['rider' => $rider, 'galop' => $galop, 'level' => $level, 'phases' => $phases];
    }

    /** Exercices des 18 derniers mois du cavalier, notés par étape. */
    private function history(User $rider, ?int $horseId, ?int $excludeSessionId): Collection
    {
        $rows = SessionExercise::query()
            ->join('riding_sessions', 'riding_sessions.id', '=', 'session_exercises.riding_session_id')
            ->whereNull('riding_sessions.deleted_at')
            ->when($excludeSessionId, fn ($q) => $q->where('riding_sessions.id', '!=', $excludeSessionId))
            ->where(fn ($q) => $q->where('riding_sessions.rider_id', $rider->id)->orWhere(fn ($w) => $w->whereNull('riding_sessions.rider_id')->where('riding_sessions.created_by', $rider->id)))
            ->where('riding_sessions.scheduled_at', '>=', now()->subMonths(18))
            ->where('riding_sessions.scheduled_at', '<=', now())
            ->whereNotNull('session_exercises.exercise_id')
            ->where('session_exercises.is_break', false)
            ->get(['session_exercises.exercise_id', 'session_exercises.phase', 'session_exercises.status', 'riding_sessions.scheduled_at', 'riding_sessions.horse_id', 'riding_sessions.status as session_status']);

        return $rows->groupBy('phase')->map(fn ($items) => $items->groupBy('exercise_id')->map(function ($uses, $exerciseId) use ($horseId) {
            $score = 0;
            foreach ($uses as $u) {
                $days = (int) abs(now()->diffInDays($u->scheduled_at));
                $done = $u->status === 'done' ? 1.0 : ($u->status === 'skipped' ? 0.3 : ($u->session_status === 'completed' ? 0.8 : 0.5));
                $score += $done * (0.5 ** ($days / 60)) * ($horseId && (int) $u->horse_id === $horseId ? 1.3 : 1.0);
            }
            $last = $uses->max('scheduled_at');

            return ['exercise_id' => (int) $exerciseId, 'count' => $uses->count(), 'score' => $score, 'days' => $last ? (int) abs(now()->diffInDays($last)) : null];
        })->values());
    }
}
