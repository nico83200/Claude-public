<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use RuntimeException;

/**
 * Appel à l'API Claude (SDK officiel PHP), isolé pour être remplacé dans les tests.
 * Réponse normalisée : textes, appels d'outils, contenu brut à renvoyer tel quel au tour suivant.
 */
class ClaudeGateway
{
    public function __construct(private AssistantConfig $config) {}

    public function configured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @return array{stop_reason: ?string, texts: string[], tool_uses: array<int, array{id: string, name: string, input: array}>, raw: array, usage: array{input: int, output: int}, model: string}
     */
    public function send(array $system, array $messages, array $tools): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Assistant non configuré (clé Anthropic manquante).');
        }
        $client = new Client(apiKey: config('services.anthropic.key'), baseUrl: config('services.anthropic.base_url') ?: null, requestOptions: ['timeout' => 90, 'maxRetries' => 2]);
        $model = $this->config->model();
        // Repli automatique sur un autre modèle en cas de refus de sécurité (Opus / Sonnet uniquement).
        $fallback = in_array($model, ['claude-opus-5-5', 'claude-sonnet-5-5'], true);

        try {
            $message = $client->beta->messages->create(
                model: $model,
                maxTokens: 16000,
                system: $system,
                messages: $messages,
                tools: $tools,
                outputConfig: ['effort' => $this->config->effort()],
                fallbacks: $fallback ? 'default' : null,
                betas: $fallback ? ['server-side-fallback-2026-07-01'] : null,
            );
        } catch (AuthenticationException) {
            throw new RuntimeException('Clé Anthropic refusée : vérifiez-la dans Administration › Intégrations.');
        } catch (RateLimitException) {
            throw new RuntimeException('L\'assistant est très sollicité, réessayez dans un instant.');
        } catch (BadRequestException $e) {
            // Détail technique dans le journal des erreurs (Administration › Erreurs), message simple pour l'utilisateur.
            report($e);
            throw new RuntimeException('L\'assistant n\'a pas pu traiter cette consigne (erreur technique signalée à l\'administrateur). Réessayez plus tard ou faites-le depuis l\'écran concerné.');
        } catch (APIStatusException $e) {
            report($e);
            throw new RuntimeException('L\'assistant est momentanément indisponible. Réessayez dans quelques instants.');
        } catch (APIConnectionException) {
            throw new RuntimeException('Impossible de joindre Claude (réseau).');
        }

        $texts = [];
        $toolUses = [];
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $texts[] = $block->text;
            } elseif ($block->type === 'tool_use') {
                $toolUses[] = ['id' => $block->id, 'name' => $block->name, 'input' => (array) $block->input];
            }
        }

        return [
            'stop_reason' => $message->stopReason,
            'texts' => $texts,
            'tool_uses' => $toolUses,
            'raw' => $message->content,
            'usage' => ['input' => $message->usage->inputTokens + (int) $message->usage->cacheReadInputTokens + (int) $message->usage->cacheCreationInputTokens, 'output' => $message->usage->outputTokens],
            'model' => $message->model,
        ];
    }
}
