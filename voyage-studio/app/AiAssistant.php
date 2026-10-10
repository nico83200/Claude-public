<?php
declare(strict_types=1);

use Anthropic\Client as AnthropicClient;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Assistant IA (Claude, API Anthropic) :
 *  - extract()  : lit une capture d'écran / un PDF de confirmation / un texte d'e-mail
 *                 et en déduit des prestations prêtes à ajouter au dossier ;
 *  - suggest()  : relit une variante de voyage et signale les oublis / incohérences.
 *
 * Optionnel : actif uniquement si vendor/ est présent et qu'une clé API est configurée (config.php → 'ai').
 */
final class AiAssistant
{
    public const DEFAULT_MODEL = 'claude-opus-5-5';
    private const MAX_FILE_BYTES = 8 * 1024 * 1024;
    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    private array $cfg;

    public function __construct(?array $cfg)
    {
        $this->cfg = $cfg ?? [];
    }

    public static function vendorAutoload(): string
    {
        return VS_ROOT . '/vendor/autoload.php';
    }

    public function status(): array
    {
        $hasSdk = is_file(self::vendorAutoload());
        $hasKey = !empty($this->cfg['api_key']);
        return [
            'enabled' => $hasSdk && $hasKey,
            'sdk' => $hasSdk,
            'key' => $hasKey,
            'model' => $this->cfg['model'] ?? self::DEFAULT_MODEL,
        ];
    }

    private function client(): AnthropicClient
    {
        $st = $this->status();
        if (!$st['sdk']) {
            throw new HttpException(503, "Module IA non installé : dossier vendor/ absent (voir README, section « Assistant IA »).");
        }
        if (!$st['key']) {
            throw new HttpException(503, "Assistant IA non configuré : ajoutez votre clé API Anthropic dans config.php ('ai' => ['api_key' => …]).");
        }
        require_once self::vendorAutoload();
        @set_time_limit(180);
        return new AnthropicClient(apiKey: $this->cfg['api_key']);
    }

    /* ------------------------------------------------------------ Extraction */

    /**
     * @param array $files   [['name' => ..., 'type' => 'image/png'|'application/pdf', 'data' => base64], ...]
     * @param string $text   texte libre collé (e-mail de confirmation, devis fournisseur…)
     * @param array $context informations du dossier (dates, participants, destination) pour lever les ambiguïtés
     */
    public function extract(array $files, string $text, array $context): array
    {
        $content = [];
        foreach (array_slice($files, 0, 5) as $f) {
            $type = (string)($f['type'] ?? '');
            $data = preg_replace('/\s+/', '', (string)($f['data'] ?? ''));
            if ($data === '' || base64_decode($data, true) === false) {
                throw new ValidationException(['files' => 'Fichier illisible : ' . ($f['name'] ?? '?')]);
            }
            if (strlen($data) * 3 / 4 > self::MAX_FILE_BYTES) {
                throw new ValidationException(['files' => 'Fichier trop volumineux (8 Mo max) : ' . ($f['name'] ?? '?')]);
            }
            if (in_array($type, self::IMAGE_TYPES, true)) {
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $type, 'data' => $data]];
            } elseif ($type === 'application/pdf') {
                $content[] = ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]];
            } else {
                throw new ValidationException(['files' => 'Format non pris en charge (PNG, JPEG, WebP, GIF ou PDF) : ' . ($f['name'] ?? '?')]);
            }
        }
        $text = trim(mb_substr($text, 0, 30000));
        if (!$content && $text === '') {
            throw new ValidationException(['files' => 'Ajoutez une capture, un PDF ou collez un texte']);
        }

        $prompt = "Voici un ou plusieurs documents fournis par un agent de voyage (capture d'écran d'un site de réservation, "
            . "confirmation, e-mail, devis fournisseur…). Extrais chaque prestation de voyage qu'ils décrivent pour l'ajouter à son dossier.\n\n"
            . "Contexte du dossier (pour compléter les années manquantes, le nombre de voyageurs, etc.) :\n"
            . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . ($text !== '' ? "Texte collé par l'agent :\n<<<\n$text\n>>>\n\n" : '')
            . "Règles :\n"
            . "- Une prestation par trajet (aller et retour = 2 vols ; un vol avec correspondance = 1 vol avec escales ≥ 1, horaires du premier départ et de la dernière arrivée), une par hébergement, croisière, transfert, location, activité ou assurance.\n"
            . "- Dates au format AAAA-MM-JJ, heures HH:MM en heure locale telles qu'affichées. Codes IATA des aéroports en majuscules si identifiables.\n"
            . "- prix_total = montant TOTAL affiché pour la prestation (toutes personnes, toutes nuits), dans la devise affichée (code ISO). Si seul un prix par personne ou par nuit est visible, renseigne prix_unitaire et unite en conséquence et laisse prix_total à null.\n"
            . "- taxes = part du prix_total correspondant à des taxes ou frais (taxes aéroport, portuaires…) quand elle est détaillée. Les montants payables EN PLUS ou sur place (ex. taxe de séjour à régler à l'hôtel) ne vont ni dans prix_total ni dans taxes : mentionne-les dans 'remarques'.\n"
            . "- N'invente rien : chaîne vide \"\" pour un texte absent ou illisible, null pour un nombre inconnu. Signale les incertitudes dans 'remarques'.\n"
            . "- libelle : court et parlant pour un client (ex. « Vol Paris CDG → Bangkok BKK — Thai Airways TG931 », « Hôtel Riva Surya 4* — 5 nuits, chambre Deluxe »).";

        $content[] = ['type' => 'text', 'text' => $prompt];

        $itemProps = [
            'type' => ['type' => 'string', 'enum' => array_keys(Schema::ITEM_TYPES)],
            'libelle' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'fournisseur' => ['type' => 'string', 'description' => 'Compagnie, hôtel, croisiériste, loueur… qui fournit la prestation'],
            'date_debut' => ['type' => 'string'],
            'heure_debut' => ['type' => 'string'],
            'date_fin' => ['type' => 'string'],
            'heure_fin' => ['type' => 'string'],
            'lieu_depart' => ['type' => 'string'],
            'lieu_arrivee' => ['type' => 'string'],
            'compagnie' => ['type' => 'string'],
            'numero' => ['type' => 'string', 'description' => 'N° de vol ou de train'],
            'classe' => ['type' => 'string'],
            'escales' => ['type' => ['integer', 'null']],
            'duree_min' => ['type' => ['integer', 'null'], 'description' => 'Durée totale en minutes si affichée'],
            'bagages' => ['type' => 'string'],
            'navire' => ['type' => 'string'],
            'categorie' => ['type' => 'string'],
            'chambre' => ['type' => 'string'],
            'pension' => ['type' => 'string', 'description' => 'RO, BB, HB, FB ou AI'],
            'mode_transfert' => ['type' => 'string', 'description' => 'prive, partage ou chauffeur'],
            'unite' => ['type' => 'string', 'description' => 'Une valeur parmi : ' . implode(', ', array_keys(Schema::UNITS))],
            'quantite' => ['type' => ['number', 'null']],
            'prix_unitaire' => ['type' => ['number', 'null']],
            'prix_total' => ['type' => ['number', 'null']],
            'taxes' => ['type' => ['number', 'null']],
            'devise' => ['type' => 'string'],
            'ref_reservation' => ['type' => 'string'],
            'remarques' => ['type' => 'string', 'description' => 'Incertitudes, conditions particulières, éléments à vérifier'],
        ];
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'properties' => $itemProps,
                    'required' => array_keys($itemProps), 'additionalProperties' => false,
                ]],
                'commentaire' => ['type' => 'string', 'description' => 'Synthèse courte pour l\'agent'],
            ],
            'required' => ['items', 'commentaire'],
            'additionalProperties' => false,
        ];

        $data = $this->callJson($content, $schema, $this->cfg['effort_extract'] ?? 'low');
        $items = [];
        foreach ($data['items'] ?? [] as $it) {
            $items[] = $this->normalizeItem((array)$it);
        }
        return ['items' => $items, 'commentaire' => ($data['commentaire'] ?? '') ?: null];
    }

    /** Convertit la sortie IA en données compatibles avec l'entité 'items' (validation permissive). */
    private function normalizeItem(array $it): array
    {
        $fields = Schema::entity('items')['fields'];
        $out = [];
        foreach ($it as $k => $v) {
            if (!isset($fields[$k]) || $v === null || $v === '') {
                continue;
            }
            try {
                $v = Repository::normalize($fields[$k], $v);
            } catch (InvalidArgumentException) {
                continue;
            }
            if ($v !== null) {
                $out[$k] = $v;
            }
        }
        $out['type'] ??= 'autre';
        $out['libelle'] ??= Schema::ITEM_TYPES[$out['type']] ?? 'Prestation';
        if (!empty($it['devise'])) {
            $out['devise'] = strtoupper(substr((string)$it['devise'], 0, 3));
        }
        // Prix total connu : on le traduit en forfait (prix unitaire = total)
        if (isset($it['prix_total']) && is_numeric($it['prix_total'])) {
            $out['unite'] = 'forfait';
            $out['quantite'] = 1;
            $out['prix_unitaire'] = round((float)$it['prix_total'] - (float)($it['taxes'] ?? 0), 2);
        }
        $notes = array_filter([
            !empty($it['fournisseur']) ? 'Fournisseur indiqué : ' . $it['fournisseur'] : null,
            $it['remarques'] ?? null,
        ]);
        if ($notes) {
            $out['notes'] = "[Import IA] " . implode("\n", $notes);
        }
        $out['_fournisseur'] = ($it['fournisseur'] ?? '') ?: null;
        $out['statut'] = !empty($out['ref_reservation']) ? 'confirme' : 'a_demander';
        return $out;
    }

    /* ------------------------------------------------------------ Suggestions */

    public function suggest(array $tripSummary): array
    {
        $prompt = "Tu es un agent de voyage expérimenté qui relit le dossier d'un confrère avant envoi du devis au client.\n"
            . "Voici le dossier (JSON) :\n" . json_encode($tripSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
            . "Liste les oublis, incohérences et points de vigilance concrets : prestations manquantes (transferts, nuits non couvertes, "
            . "vol retour, assurance, formalités, location, parking, pré/post-acheminement, excursions incontournables, repas), "
            . "horaires serrés (correspondances, arrivée tardive, check-in), saisonnalité/climat, fêtes locales, conseils de vente additionnelle pertinents. "
            . "Ne répète pas ce qui est déjà correctement prévu. 10 suggestions maximum, les plus utiles d'abord. Rédige en français, de façon concise.";
        $schema = [
            'type' => 'object',
            'properties' => [
                'suggestions' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'niveau' => ['type' => 'string', 'enum' => ['important', 'conseil', 'info']],
                        'titre' => ['type' => 'string'],
                        'detail' => ['type' => 'string'],
                        'type_prestation' => ['type' => 'string',
                            'description' => 'Si la suggestion consiste à ajouter une prestation, son type parmi : ' . implode(', ', array_keys(Schema::ITEM_TYPES)) . ' ; sinon chaîne vide'],
                    ],
                    'required' => ['niveau', 'titre', 'detail', 'type_prestation'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['suggestions'],
            'additionalProperties' => false,
        ];
        $data = $this->callJson([['type' => 'text', 'text' => $prompt]], $schema, $this->cfg['effort_suggest'] ?? 'medium');
        return $data['suggestions'] ?? [];
    }

    /* ------------------------------------------------------------ Appel API */

    private function callJson(array $content, array $schema, string $effort): array
    {
        $client = $this->client();
        try {
            $message = $client->beta->messages->create(
                model: $this->cfg['model'] ?? self::DEFAULT_MODEL,
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => $content]],
                outputConfig: [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                // Si un filtre de sécurité refuse la requête, l'API la rejoue sur le modèle de repli recommandé.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new HttpException(502, 'Clé API Anthropic refusée : vérifiez config.php');
        } catch (RateLimitException) {
            throw new HttpException(429, 'Limite de requêtes IA atteinte, réessayez dans une minute');
        } catch (BadRequestException $e) {
            throw new HttpException(400, 'Requête IA refusée : ' . $e->getMessage());
        } catch (APIStatusException $e) {
            throw new HttpException(502, 'Service IA indisponible (' . ($e->type?->value ?? 'erreur') . ')');
        } catch (APIConnectionException) {
            throw new HttpException(502, 'Impossible de joindre le service IA (connexion sortante bloquée par l\'hébergeur ?)');
        }

        if ($message->stopReason === 'refusal') {
            throw new HttpException(422, "L'IA n'a pas pu traiter ce document.");
        }
        if ($message->stopReason === 'max_tokens') {
            throw new HttpException(422, 'Document trop long : découpez-le en plusieurs imports.');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        throw new HttpException(502, "Réponse de l'IA inexploitable");
    }
}
