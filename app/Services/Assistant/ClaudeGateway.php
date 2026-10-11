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

    /**
     * Lit une facture (photo ou PDF) et renvoie ses informations sous une forme imposée (schéma JSON).
     * Rien n'est enregistré ici : la personne vérifie et valide avant toute création.
     *
     * @param  string  $mediaType  image/jpeg, image/png, image/webp, image/gif ou application/pdf
     * @return array{data: array, usage: array{input: int, output: int}, model: string}
     */
    public function readInvoice(string $bytes, string $mediaType, array $horseNames, array $expenseKeys, array $careKeys): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Assistant non configuré (clé Anthropic manquante).');
        }
        $client = new Client(apiKey: config('services.anthropic.key'), baseUrl: config('services.anthropic.base_url') ?: null, requestOptions: ['timeout' => 90, 'maxRetries' => 2]);
        $model = $this->config->model();
        $fallback = in_array($model, ['claude-opus-5-5', 'claude-sonnet-5-5'], true);
        $file = $mediaType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($bytes)]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => base64_encode($bytes)]];
        $instructions = "Voici une facture ou un ticket lié à un cheval (vétérinaire, maréchal-ferrant, pension, aliment, sellerie…).\n"
            .'Chevaux de la personne : '.($horseNames ? implode(', ', $horseNames) : 'aucun')."\n"
            .'Extrais : le fournisseur, la date de la facture (AAAA-MM-JJ), le montant total TTC payé, la devise, la catégorie de dépense, '
            ."le type de soin si la facture concerne un acte sur le cheval (sinon « none »), le nom du cheval s'il apparaît (exactement comme dans la liste, sinon chaîne vide), "
            ."un résumé en français de 12 mots maximum, et les lignes principales (5 au plus). Laisse une chaîne vide ou 0 pour ce qui est illisible ; n'invente rien. "
            .'Indique ta confiance globale.';
        $schema = [
            'type' => 'object',
            'properties' => [
                'is_invoice' => ['type' => 'boolean'],
                'supplier' => ['type' => 'string'],
                'invoice_date' => ['type' => 'string'],
                'total_amount' => ['type' => 'number'],
                'currency' => ['type' => 'string'],
                'expense_category' => ['type' => 'string', 'enum' => $expenseKeys],
                'care_category' => ['type' => 'string', 'enum' => [...$careKeys, 'none']],
                'horse_name' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'amount' => ['type' => 'number']], 'required' => ['label', 'amount'], 'additionalProperties' => false]],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
            ],
            'required' => ['is_invoice', 'supplier', 'invoice_date', 'total_amount', 'currency', 'expense_category', 'care_category', 'horse_name', 'summary', 'lines', 'confidence'],
            'additionalProperties' => false,
        ];

        try {
            $message = $client->beta->messages->create(
                model: $model,
                maxTokens: 4000,
                messages: [['role' => 'user', 'content' => [$file, ['type' => 'text', 'text' => $instructions]]]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                fallbacks: $fallback ? 'default' : null,
                betas: $fallback ? ['server-side-fallback-2026-07-01'] : null,
            );
        } catch (AuthenticationException) {
            throw new RuntimeException('Clé Anthropic refusée : vérifiez-la dans Administration › Intégrations.');
        } catch (RateLimitException) {
            throw new RuntimeException('L\'assistant est très sollicité, réessayez dans un instant.');
        } catch (BadRequestException $e) {
            report($e);
            throw new RuntimeException('Ce fichier n\'a pas pu être lu. Essayez une photo plus nette, ou saisissez la dépense à la main.');
        } catch (APIStatusException $e) {
            report($e);
            throw new RuntimeException('L\'assistant est momentanément indisponible. Réessayez dans quelques instants.');
        } catch (APIConnectionException) {
            throw new RuntimeException('Impossible de joindre Claude (réseau).');
        }
        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Ce document n\'a pas pu être lu. Saisissez la dépense à la main.');
        }
        $data = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                break;
            }
        }
        if (! is_array($data)) {
            throw new RuntimeException('Lecture de la facture incomplète. Réessayez ou saisissez la dépense à la main.');
        }

        return [
            'data' => $data,
            'usage' => ['input' => $message->usage->inputTokens + (int) $message->usage->cacheReadInputTokens + (int) $message->usage->cacheCreationInputTokens, 'output' => $message->usage->outputTokens],
            'model' => $message->model,
        ];
    }
}
