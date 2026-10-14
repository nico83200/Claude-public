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

    /**
     * Lit une ordonnance ou un compte rendu vétérinaire (photo ou PDF) : soin, traitements prescrits,
     * prochain contrôle. Rien n'est enregistré ici : la personne vérifie avant toute création.
     *
     * @return array{data: array, usage: array{input: int, output: int}, model: string}
     */
    public function readPrescription(string $bytes, string $mediaType, array $horseNames, array $careKeys): array
    {
        $instructions = "Voici une ordonnance ou un compte rendu de vétérinaire (ou d'un autre professionnel équin) concernant un cheval.\n"
            .'Chevaux de la personne : '.($horseNames ? implode(', ', $horseNames) : 'aucun')."\n"
            ."Extrais : le praticien (nom et éventuellement structure), la date de la visite (AAAA-MM-JJ), le type de soin, le nom du cheval s'il apparaît (exactement comme dans la liste, sinon chaîne vide), "
            ."le motif, le diagnostic communiqué, les consignes, la date du prochain contrôle si elle est indiquée (sinon chaîne vide) ou un délai en jours (sinon 0), "
            ."et chaque médicament prescrit : produit, posologie telle qu'écrite (jamais recalculée), fréquence, nombre de prises par jour, durée en jours (0 si non précisée), voie d'administration. "
            ."Recopie fidèlement ; laisse vide ce qui est illisible ou absent ; n'invente rien et ne donne aucun avis médical. Indique ta confiance globale.";
        $treatment = ['type' => 'object', 'properties' => [
            'product' => ['type' => 'string'], 'dosage' => ['type' => 'string'], 'frequency' => ['type' => 'string'],
            'times_per_day' => ['type' => 'integer'], 'duration_days' => ['type' => 'integer'], 'route' => ['type' => 'string'],
        ], 'required' => ['product', 'dosage', 'frequency', 'times_per_day', 'duration_days', 'route'], 'additionalProperties' => false];
        $schema = ['type' => 'object', 'properties' => [
            'is_prescription' => ['type' => 'boolean'],
            'practitioner' => ['type' => 'string'],
            'visit_date' => ['type' => 'string'],
            'care_category' => ['type' => 'string', 'enum' => $careKeys],
            'horse_name' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
            'diagnosis' => ['type' => 'string'],
            'instructions' => ['type' => 'string'],
            'next_check_on' => ['type' => 'string'],
            'next_check_days' => ['type' => 'integer'],
            'treatments' => ['type' => 'array', 'items' => $treatment],
            'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
        ], 'required' => ['is_prescription', 'practitioner', 'visit_date', 'care_category', 'horse_name', 'reason', 'diagnosis', 'instructions', 'next_check_on', 'next_check_days', 'treatments', 'confidence'], 'additionalProperties' => false];

        return $this->structured([$this->fileBlock($bytes, $mediaType), ['type' => 'text', 'text' => $instructions]], $schema,
            'Ce document n\'a pas pu être lu. Essayez une photo plus nette, ou saisissez le soin à la main.');
    }

    /**
     * Propose un plan de séance (échauffement, travail, retour au calme) à partir du contexte
     * (cheval, cavalier, objectif, derniers bilans) et de la bibliothèque d'exercices.
     *
     * @return array{data: array, usage: array{input: int, output: int}, model: string}
     */
    public function planSession(string $context, array $exerciseNames): array
    {
        $item = ['type' => 'object', 'properties' => [
            'name' => ['type' => 'string'], 'minutes' => ['type' => 'integer'], 'note' => ['type' => 'string'],
        ], 'required' => ['name', 'minutes', 'note'], 'additionalProperties' => false];
        $schema = ['type' => 'object', 'properties' => [
            'summary' => ['type' => 'string'],
            'warmup' => ['type' => 'array', 'items' => $item],
            'complementary' => ['type' => 'array', 'items' => $item],
            'main' => ['type' => 'array', 'items' => $item],
            'cooldown' => ['type' => 'array', 'items' => $item],
        ], 'required' => ['summary', 'warmup', 'main', 'complementary', 'cooldown'], 'additionalProperties' => false];
        $prompt = "Tu aides un cavalier à préparer une séance d'équitation. Propose un plan réaliste et progressif, adapté au niveau, "
            ."à la durée prévue et aux points à retravailler. Utilise en priorité les exercices de la bibliothèque (nom recopié exactement) ; "
            ."n'ajoute un exercice hors bibliothèque que s'il est indispensable. 2 à 3 exercices d'échauffement (warmup), 2 à 4 de travail principal (main), 0 à 2 de travail complémentaire ou d'étirements (complementary), 1 à 2 de retour au calme (cooldown). "
            ."La somme des minutes doit être proche de la durée prévue. Note courte (10 mots max) par exercice. Résumé en une phrase. Jamais de conseil vétérinaire.\n\n"
            .$context."\n\nBibliothèque d'exercices :\n- ".implode("\n- ", array_slice($exerciseNames, 0, 300));

        return $this->structured([['type' => 'text', 'text' => $prompt]], $schema, 'Le plan n\'a pas pu être préparé. Réessayez dans un instant.');
    }

    /**
     * Rédige un court texte en français (récapitulatif, synthèse) à partir de données fournies.
     *
     * @return array{text: string, usage: array{input: int, output: int}, model: string}
     */
    public function write(string $system, string $prompt, int $maxTokens = 1200): array
    {
        // Format imposé (un seul champ « text ») : la réflexion du modèle ne peut pas se mêler au texte livré
        // (sans cela, à effort bas, des notes comme « wait, I must not… » apparaissaient dans la réponse).
        // La limite couvre aussi la réflexion : 4000 au moins, pour ne pas couper le texte.
        $message = $this->call(['system' => $system, 'maxTokens' => max($maxTokens, 4000), 'messages' => [['role' => 'user', 'content' => $prompt]],
            'outputConfig' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['text'],
                'properties' => ['text' => ['type' => 'string', 'description' => 'Le texte final, prêt à être affiché, entièrement en français.']],
            ]]]],
            'Le texte n\'a pas pu être rédigé.');
        $raw = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $raw .= $block->text;
            }
        }
        if ($message->stopReason === 'refusal' || trim($raw) === '') {
            throw new RuntimeException('Le texte n\'a pas pu être rédigé.');
        }
        $data = json_decode($raw, true);
        $text = is_array($data) && is_string($data['text'] ?? null) ? $data['text'] : $raw;
        if (trim($text) === '') {
            throw new RuntimeException('Le texte n\'a pas pu être rédigé.');
        }

        // truncated : réponse coupée par la limite de longueur (la dernière phrase peut être incomplète).
        return ['text' => trim($text), 'usage' => $this->usage($message), 'model' => $message->model, 'truncated' => $message->stopReason === 'max_tokens'];
    }

    /** Réponse au format imposé (schéma JSON). */
    private function structured(array $content, array $schema, string $unreadable): array
    {
        $message = $this->call(['maxTokens' => 4000, 'messages' => [['role' => 'user', 'content' => $content]],
            'outputConfig' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]]], $unreadable);
        if ($message->stopReason === 'refusal') {
            throw new RuntimeException($unreadable);
        }
        $data = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                break;
            }
        }
        if (! is_array($data)) {
            throw new RuntimeException($unreadable);
        }

        return ['data' => $data, 'usage' => $this->usage($message), 'model' => $message->model];
    }

    /** Appel commun : modèle réglé, repli automatique, erreurs traduites pour l'utilisateur. */
    private function call(array $params, string $badRequest)
    {
        if (! $this->configured()) {
            throw new RuntimeException('Assistant non configuré (clé Anthropic manquante).');
        }
        $client = new Client(apiKey: config('services.anthropic.key'), baseUrl: config('services.anthropic.base_url') ?: null, requestOptions: ['timeout' => 90, 'maxRetries' => 2]);
        $model = $this->config->model();
        $fallback = in_array($model, ['claude-opus-5-5', 'claude-sonnet-5-5'], true);
        try {
            return $client->beta->messages->create(...$params + [
                'model' => $model,
                'fallbacks' => $fallback ? 'default' : null,
                'betas' => $fallback ? ['server-side-fallback-2026-07-01'] : null,
            ]);
        } catch (AuthenticationException) {
            throw new RuntimeException('Clé Anthropic refusée : vérifiez-la dans Administration › Intégrations.');
        } catch (RateLimitException) {
            throw new RuntimeException('L\'assistant est très sollicité, réessayez dans un instant.');
        } catch (BadRequestException $e) {
            report($e);
            throw new RuntimeException($badRequest);
        } catch (APIStatusException $e) {
            report($e);
            throw new RuntimeException('L\'assistant est momentanément indisponible. Réessayez dans quelques instants.');
        } catch (APIConnectionException) {
            throw new RuntimeException('Impossible de joindre Claude (réseau).');
        }
    }

    private function fileBlock(string $bytes, string $mediaType): array
    {
        return $mediaType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($bytes)]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mediaType, 'data' => base64_encode($bytes)]];
    }

    private function usage($message): array
    {
        return ['input' => $message->usage->inputTokens + (int) $message->usage->cacheReadInputTokens + (int) $message->usage->cacheCreationInputTokens, 'output' => $message->usage->outputTokens];
    }
}
