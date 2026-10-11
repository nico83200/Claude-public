<?php

namespace Tests\Support;

use App\Services\Assistant\ClaudeGateway;

/** Claude simulé : renvoie des réponses scriptées et mémorise les requêtes reçues. */
class FakeClaude extends ClaudeGateway
{
    public array $calls = [];

    /** @param array<int, array> $script réponses successives : ['tools' => [[name, input], ...]] ou ['text' => '...'] */
    public function __construct(private array $script) {}

    public function configured(): bool
    {
        return true;
    }

    public function send(array $system, array $messages, array $tools): array
    {
        $this->calls[] = compact('system', 'messages', 'tools');
        $next = array_shift($this->script) ?? ['text' => 'Terminé.'];
        if (isset($next['tools'])) {
            $uses = [];
            foreach ($next['tools'] as $i => [$name, $input]) {
                $uses[] = ['id' => 'toolu_'.count($this->calls).'_'.$i, 'name' => $name, 'input' => $input];
            }

            return ['stop_reason' => 'tool_use', 'texts' => [], 'tool_uses' => $uses, 'raw' => array_map(fn ($u) => ['type' => 'tool_use', 'id' => $u['id'], 'name' => $u['name'], 'input' => $u['input']], $uses), 'usage' => ['input' => 1000, 'output' => 100], 'model' => 'claude-opus-5-5'];
        }

        return ['stop_reason' => $next['stop'] ?? 'end_turn', 'texts' => [$next['text']], 'tool_uses' => [], 'raw' => [['type' => 'text', 'text' => $next['text']]], 'usage' => ['input' => 1000, 'output' => 50], 'model' => 'claude-opus-5-5'];
    }
}
