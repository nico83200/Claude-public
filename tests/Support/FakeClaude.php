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

    /** Données renvoyées par la prochaine lecture de facture (null : échec de lecture). */
    public ?array $invoice = null;

    public function readInvoice(string $bytes, string $mediaType, array $horseNames, array $expenseKeys, array $careKeys): array
    {
        $this->calls[] = ['invoice' => compact('mediaType', 'horseNames') + ['size' => strlen($bytes)]];
        if ($this->invoice === null) {
            throw new \RuntimeException('Ce fichier n\'a pas pu être lu. Essayez une photo plus nette, ou saisissez la dépense à la main.');
        }

        return ['data' => $this->invoice, 'usage' => ['input' => 1500, 'output' => 120], 'model' => 'claude-opus-5-5'];
    }

    /** Données de la prochaine lecture d'ordonnance (null : échec). */
    public ?array $prescription = null;

    public function readPrescription(string $bytes, string $mediaType, array $horseNames, array $careKeys): array
    {
        $this->calls[] = ['prescription' => compact('mediaType', 'horseNames')];
        if ($this->prescription === null) {
            throw new \RuntimeException('Ce document n\'a pas pu être lu. Essayez une photo plus nette, ou saisissez le soin à la main.');
        }

        return ['data' => $this->prescription, 'usage' => ['input' => 1500, 'output' => 200], 'model' => 'claude-opus-5-5'];
    }

    /** Plan renvoyé par la prochaine demande de plan de séance (null : échec). */
    public ?array $plan = null;

    public function planSession(string $context, array $exerciseNames): array
    {
        $this->calls[] = ['plan' => compact('context', 'exerciseNames')];
        if ($this->plan === null) {
            throw new \RuntimeException('Le plan n\'a pas pu être préparé. Réessayez dans un instant.');
        }

        return ['data' => $this->plan, 'usage' => ['input' => 2000, 'output' => 300], 'model' => 'claude-opus-5-5'];
    }

    /** Texte renvoyé par la prochaine rédaction (null : échec). */
    public ?string $written = null;

    public function write(string $system, string $prompt, int $maxTokens = 1200): array
    {
        $this->calls[] = ['write' => compact('system', 'prompt')];
        if ($this->written === null) {
            throw new \RuntimeException('Le texte n\'a pas pu être rédigé.');
        }

        return ['text' => $this->written, 'usage' => ['input' => 800, 'output' => 120], 'model' => 'claude-opus-5-5'];
    }
}
