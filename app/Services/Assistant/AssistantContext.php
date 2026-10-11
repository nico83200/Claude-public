<?php

namespace App\Services\Assistant;

use App\Models\AssistantAction;
use App\Models\AssistantRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** État d'une consigne en cours d'exécution. */
class AssistantContext
{
    /** @var AssistantAction[] */
    public array $actions = [];

    /** Question posée à l'utilisateur (outil ask_user). */
    public ?array $question = null;

    public function __construct(
        public User $user,
        public Organization $organization,
        public AssistantRequest $request,
        public ?int $contextHorseId = null,
    ) {}

    public function record(string $type, ?Model $subject, string $summary, ?string $url, ?array $undo): AssistantAction
    {
        $action = $this->request->actions()->create([
            'type' => $type, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 250), 'url' => $url, 'undo' => $undo,
        ]);

        return $this->actions[] = $action;
    }
}
