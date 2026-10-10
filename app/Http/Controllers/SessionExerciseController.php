<?php

namespace App\Http\Controllers;

use App\Models\RidingSession;
use App\Models\SessionExercise;
use App\Services\RidingSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionExerciseController extends Controller
{
    public function __construct(private RidingSessionService $sessions) {}

    public function store(Request $request, RidingSession $session)
    {
        $this->authorize($request, $session, false);
        $data = $request->validate([
            'items' => ['required', 'array', 'max:40'],
            'items.*.exercise_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:150'],
            'items.*.phase' => ['nullable', Rule::in(array_keys(RidingSession::PHASES))],
            'items.*.planned_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'items.*.planned_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'],
            'items.*.instructions' => ['nullable', 'string', 'max:2000'],
            'items.*.is_break' => ['nullable', 'boolean'],
        ]);
        $this->sessions->addItems($session, $request->user(), $data['items']);

        return back()->with('success', count($data['items']) > 1 ? 'Exercices ajoutés.' : 'Exercice ajouté.');
    }

    /** Mise à jour d'une ligne (préparation ou réalisation en séance). */
    public function update(Request $request, RidingSession $session, SessionExercise $item)
    {
        $this->authorize($request, $session, true);
        $data = $request->validate([
            'phase' => ['nullable', Rule::in(array_keys(RidingSession::PHASES))],
            'planned_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'planned_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['pending', 'done', 'skipped'])],
            'done_repetitions' => ['nullable', 'integer', 'min:0', 'max:500'],
            'actual_minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'difficulty' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if (isset($data['status']) && $data['status'] !== $item->status) {
            $data['status_changed_at'] = now();
        }
        $item->update(array_filter($data, fn ($v, $k) => $v !== null || in_array($k, ['note', 'instructions'], true), ARRAY_FILTER_USE_BOTH));

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'item' => $item->fresh()]);
        }

        return back();
    }

    public function move(Request $request, RidingSession $session, SessionExercise $item)
    {
        $this->authorize($request, $session, false);
        $direction = $request->input('direction') === 'up' ? -1 : 1;
        $siblings = $session->items()->where('phase', $item->phase)->orderBy('position')->get()->values();
        $index = $siblings->search(fn ($s) => $s->id === $item->id);
        $swap = $siblings->get($index + $direction);
        if ($swap) {
            [$a, $b] = [$item->position, $swap->position];
            if ($a === $b) {
                $b = $a + $direction;
            }
            $item->update(['position' => $b]);
            $swap->update(['position' => $a]);
        }

        return back();
    }

    public function destroy(Request $request, RidingSession $session, SessionExercise $item)
    {
        $this->authorize($request, $session, false);
        $item->delete();

        return back()->with('success', 'Exercice retiré de la séance.');
    }

    private function authorize(Request $request, RidingSession $session, bool $allowInProgressOnly): void
    {
        abort_unless($this->sessions->canEdit($request->user(), $session), 403);
        // L'historique d'une séance terminée n'est jamais réécrit.
        abort_if($session->isCompleted(), 422, 'Séance terminée : les exercices réalisés ne sont plus modifiables.');
    }
}
