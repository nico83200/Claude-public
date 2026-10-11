<?php

namespace App\Services\Assistant;

use App\Models\AssistantAction;
use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\CareReminderRule;
use App\Models\Expense;
use App\Models\FeedingPlan;
use App\Models\User;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use App\Support\Perm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Annulation d'une action de l'assistant : 10 s via le bandeau, puis 24 h
 * depuis l'historique, tant que l'élément n'a pas été modifié entre-temps.
 */
class AssistantUndo
{
    /** Tolérance entre l'action et la dernière modification de l'élément (même requête). */
    private const GRACE_SECONDS = 5;

    public function __construct(private HorseAccess $access, private Entitlements $entitlements) {}

    public function undo(AssistantAction $action, User $user): void
    {
        $action->loadMissing('request');
        if ($action->request->user_id !== $user->id) {
            throw new RuntimeException('Seule la personne qui a donné la consigne peut l\'annuler.');
        }
        if ($action->undone_at) {
            throw new RuntimeException('Cette action a déjà été annulée.');
        }
        if (! $action->isUndoable()) {
            throw new RuntimeException('Le délai d\'annulation (24 h) est dépassé : modifiez l\'élément directement.');
        }
        $undo = $action->undo;

        DB::transaction(function () use ($action, $undo, $user) {
            if (isset($undo['delete'])) {
                [$class, $id] = $undo['delete'];
                $model = $class::find($id);
                if ($model) {
                    $this->guard($model, $action, $user);
                    if ($model instanceof CareRecord) {
                        $this->undoCare($model, $undo);
                    }
                    $model->delete();
                }
            } elseif (isset($undo['restore'])) {
                [$class, $id, $before] = $undo['restore'];
                $model = $class::find($id) ?? throw new RuntimeException('L\'élément n\'existe plus.');
                $this->guard($model, $action, $user);
                unset($before['__updated_at']);
                $model->forceFill($before)->save();
            } elseif (isset($undo['feeding_new'])) {
                $data = $undo['feeding_new'];
                $plan = FeedingPlan::find($data['plan_id']);
                if ($plan) {
                    $this->guard($plan, $action, $user, checkFreshness: false);
                    $plan->entries()->delete();
                    $plan->delete();
                }
                if ($data['previous_id']) {
                    FeedingPlan::whereKey($data['previous_id'])->update(['ends_on' => $data['previous_ends_on']]);
                }
            } elseif (isset($undo['feeding_snapshot'])) {
                $data = $undo['feeding_snapshot'];
                $plan = FeedingPlan::find($data['plan_id']) ?? throw new RuntimeException('La ration n\'existe plus.');
                $this->guard($plan, $action, $user, checkFreshness: false);
                $plan->entries()->delete();
                foreach ($data['entries'] as $entry) {
                    $plan->entries()->create($entry);
                }
            } else {
                throw new RuntimeException('Cette action ne peut pas être annulée.');
            }
            $action->update(['undone_at' => now()]);
        });
        Audit::log('assistant.undone', $action, ['type' => $action->type], $action->request->organization_id);
    }

    private function undoCare(CareRecord $record, array $undo): void
    {
        CalendarEvent::where('source_type', $record->getMorphClass())->where('source_id', $record->id)->delete();
        if (! empty($undo['expense'])) {
            Expense::whereKey($undo['expense'])->delete();
        }
        if (! empty($undo['reopen'])) {
            CareRecord::whereIn('id', $undo['reopen'])->update(['next_check_done_at' => null]);
            CalendarEvent::where('source_type', $record->getMorphClass())->whereIn('source_id', $undo['reopen'])->where('status', 'done')->update(['status' => 'planned']);
        }
        if (! empty($undo['rule'])) {
            $rule = $undo['rule'];
            if ($rule['before']) {
                CareReminderRule::updateOrCreate(['horse_id' => $rule['horse_id'], 'care_category_id' => $rule['category_id']], $rule['before']);
            } else {
                CareReminderRule::where('horse_id', $rule['horse_id'])->where('care_category_id', $rule['category_id'])->delete();
            }
        }
    }

    /** L'utilisateur doit toujours avoir accès au cheval, et l'élément ne doit pas avoir été modifié depuis. */
    private function guard(Model $model, AssistantAction $action, User $user, bool $checkFreshness = true): void
    {
        $horse = $model->horse ?? null;
        if ($horse && ! in_array(Perm::HORSE_VIEW, $this->access->permissions($user, $horse), true)) {
            throw new RuntimeException('Vous n\'avez plus accès à ce cheval.');
        }
        if ($horse && ! $this->entitlements->writable($horse->organization)) {
            throw new RuntimeException('Espace en lecture seule : annulation impossible.');
        }
        if ($checkFreshness && $model->updated_at && $model->updated_at->gt($action->created_at->copy()->addSeconds(self::GRACE_SECONDS))) {
            throw new RuntimeException('L\'élément a été modifié depuis : annulez ou corrigez-le directement.');
        }
    }
}
