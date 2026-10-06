<?php
declare(strict_types=1);

/**
 * Moteur de recherche assisté.
 *
 * 1. Recherche intelligente locale (toujours disponible) : sans accents, pluriels,
 *    synonymes du métier, tolérance aux fautes de frappe, popularité dans le centre.
 * 2. Assistant IA (optionnel, Claude) : comprend les demandes en langage naturel
 *    (« de quoi désinfecter la table d'examen ») et propose les articles adaptés.
 */

const SEARCH_STOPWORDS = ['de', 'du', 'des', 'le', 'la', 'les', 'l', 'd', 'un', 'une', 'et', 'ou', 'a', 'au', 'aux', 'en',
    'pour', 'avec', 'sans', 'sur', 'dans', 'par', 'j', 'je', 'ai', 'il', 'me', 'nous', 'on', 'faut', 'faudrait', 'besoin',
    'quelque', 'chose', 'qui', 'que', 'quoi', 'mon', 'ma', 'mes', 'notre', 'nos', 'cherche', 'voudrais', 'veux',
    'commander', 'acheter', 'trouver', 'svp', 'merci', 'bonjour', 'est', 'ce', 'cet', 'cette', 'ces', 'plus', 'tres', 'y'];

/** Groupes de synonymes du métier (centres de santé, cabinets, kiné, secrétariat). */
function search_synonym_groups(): array
{
    return [
        ['gant', 'gants', 'nitrile', 'latex', 'vinyle'],
        ['compresse', 'gaze', 'tampon'],
        ['pansement', 'sparadrap', 'strip', 'plaie', 'adhesif'],
        ['desinfectant', 'antiseptique', 'desinfection', 'desinfecter', 'nettoyant', 'detergent', 'virucide', 'bactericide', 'lingette', 'surfanios', 'aniosurf'],
        ['hydroalcoolique', 'gel', 'sha', 'solution', 'friction', 'mains'],
        ['alcool', 'biseptine', 'betadine', 'chlorhexidine', 'antiseptique'],
        ['seringue', 'aiguille', 'injection', 'piqure', 'vaccin'],
        ['masque', 'ffp2', 'chirurgical', 'protection'],
        ['drap', 'draps', 'rouleau', 'divan', 'table', 'protege'],
        ['ramette', 'rame', 'feuille', 'a4', 'papier', 'impression'],
        ['stylo', 'bic', 'crayon', 'feutre', 'surligneur', 'ecrire'],
        ['toner', 'cartouche', 'encre', 'imprimante'],
        ['enveloppe', 'courrier', 'pli', 'postal'],
        ['agrafe', 'agrafeuse', 'trombone', 'classeur', 'chemise', 'pochette', 'bureau'],
        ['thermometre', 'temperature', 'fievre'],
        ['tensiometre', 'tension', 'brassard', 'pression'],
        ['abaisse', 'abaisselangue', 'langue', 'spatule'],
        ['speculum', 'gynecologie', 'gyneco'],
        ['otoscope', 'oreille', 'embout', 'speculum'],
        ['bande', 'bandage', 'contention', 'strapping', 'tape', 'taping', 'kinesio', 'kinesiotape', 'elastique'],
        ['huile', 'creme', 'massage', 'baume', 'lotion'],
        ['gel', 'echographie', 'ultrason', 'contact'],
        ['electrode', 'electrostimulation', 'tens', 'stimulation'],
        ['poubelle', 'sac', 'dechet', 'dasri', 'collecteur', 'aiguille'],
        ['savon', 'lavage', 'mains', 'essuie', 'essuiemain'],
        ['cafe', 'sucre', 'gobelet', 'touillette', 'capsule', 'dosette'],
        ['pile', 'piles', 'batterie', 'aa', 'aaa'],
        ['glace', 'froid', 'cryotherapie', 'poche', 'cold'],
        ['coton', 'ouate', 'disque'],
        ['sterile', 'sterilisation', 'autoclave', 'sachet'],
        ['bistouri', 'lame', 'scalpel', 'suture', 'fil'],
        ['urine', 'bandelette', 'analyse', 'test', 'glycemie', 'lancette'],
        ['tablier', 'blouse', 'surblouse', 'tenue', 'charlotte', 'surchaussure'],
        ['essuie', 'papier', 'mouchoir', 'ouate', 'essuietout'],
    ];
}

function search_normalize(string $s): string
{
    static $t = false;
    if ($t === false) {
        $t = class_exists('Transliterator') ? Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC') : null;
    }
    $s = mb_strtolower($s, 'UTF-8');
    if ($t) {
        $s = $t->transliterate($s);
    } else {
        $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae']);
    }
    $s = str_replace(["'", '’'], ' ', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
    return trim($s);
}

/** Racinisation légère du français (pluriels). */
function search_stem(string $w): string
{
    $len = strlen($w);
    if ($len > 4 && str_ends_with($w, 'aux')) {
        return substr($w, 0, -3) . 'al';
    }
    if ($len > 3 && (str_ends_with($w, 's') || str_ends_with($w, 'x')) && !ctype_digit($w)) {
        return substr($w, 0, -1);
    }
    return $w;
}

function search_tokens(string $s, bool $removeStopwords = true): array
{
    $out = [];
    foreach (explode(' ', search_normalize($s)) as $w) {
        if ($w === '' || ($removeStopwords && in_array($w, SEARCH_STOPWORDS, true))) {
            continue;
        }
        $out[] = search_stem($w);
    }
    return array_values(array_unique($out));
}

function search_synonyms_for(string $token): array
{
    static $index = null;
    if ($index === null) {
        $index = [];
        foreach (search_synonym_groups() as $g) {
            $stems = array_map(fn($w) => search_stem(search_normalize($w)), $g);
            foreach ($stems as $s) {
                $index[$s] = array_merge($index[$s] ?? [], $stems);
            }
        }
    }
    return array_values(array_diff(array_unique($index[$token] ?? []), [$token]));
}

/** Score de correspondance entre un mot recherché et un mot du produit (0..1). */
function search_word_match(string $q, string $w): float
{
    if ($q === $w) {
        return 1.0;
    }
    $lq = strlen($q);
    $lw = strlen($w);
    if ($lq >= 3 && str_starts_with($w, $q)) {
        return 0.85;
    }
    if ($lw >= 4 && $lq > $lw && str_starts_with($q, $w)) {
        return 0.7;
    }
    if ($lq >= 4 && $lw >= 4 && abs($lq - $lw) <= 2) {
        $d = levenshtein($q, $w);
        if ($d === 1) {
            return 0.7;
        }
        if ($d === 2 && $lq >= 7) {
            return 0.5;
        }
    }
    if ($lq >= 5 && str_contains($w, $q)) {
        return 0.5;
    }
    return 0.0;
}

/**
 * Recherche locale. Renvoie [product_id => score] trié par pertinence décroissante.
 */
function search_local(array $products, string $query, array $popularity = []): array
{
    $tokens = search_tokens($query);
    if (!$tokens) {
        $tokens = search_tokens($query, false);
    }
    if (!$tokens) {
        return [];
    }
    $qNorm = str_replace(' ', '', search_normalize($query));
    $weights = ['name' => 5.0, 'keywords' => 4.0, 'category' => 2.5, 'supplier' => 1.5, 'description' => 1.2, 'unit' => 0.5];
    $scores = [];
    $coverages = [];

    foreach ($products as $p) {
        $fields = [
            'name'        => search_tokens($p['name'] ?? '', false),
            'keywords'    => search_tokens($p['keywords'] ?? '', false),
            'category'    => search_tokens($p['category_name'] ?? '', false),
            'supplier'    => search_tokens($p['supplier_name'] ?? '', false),
            'description' => search_tokens($p['description'] ?? '', false),
            'unit'        => search_tokens($p['unit'] ?? '', false),
        ];
        $score = 0.0;
        $matched = 0;

        $bc = (string)($p['barcode'] ?? '');
        if ($bc !== '' && $bc === preg_replace('/\s+/', '', $query)) {
            $scores[(int)$p['id']] = 1000.0;
            $coverages[(int)$p['id']] = 1.0;
            continue;
        }
        $ref = str_replace(' ', '', search_normalize($p['reference'] ?? ''));
        if ($ref !== '' && strlen($qNorm) >= 3 && ($ref === $qNorm || str_starts_with($ref, $qNorm))) {
            $score += $ref === $qNorm ? 40 : 20;
            $matched = count($tokens);
        }

        foreach ($tokens as $t) {
            $best = 0.0;
            $candidates = [[$t, 1.0]];
            foreach (search_synonyms_for($t) as $syn) {
                $candidates[] = [$syn, 0.6];
            }
            foreach ($fields as $field => $words) {
                foreach ($words as $w) {
                    foreach ($candidates as [$c, $factor]) {
                        $m = search_word_match($c, $w) * $factor * $weights[$field];
                        if ($m > $best) {
                            $best = $m;
                        }
                    }
                }
            }
            if ($best > 0) {
                $matched++;
                $score += $best;
            }
        }
        if ($score <= 0) {
            continue;
        }
        $coverage = $matched / count($tokens);
        $coverages[(int)$p['id']] = $coverage;
        $score *= (0.35 + 0.65 * $coverage);
        // Phrase complète présente dans le nom : bonus
        if (str_contains(search_normalize($p['name']), search_normalize($query))) {
            $score += 6;
        }
        $score += log(1 + (int)($popularity[$p['id']] ?? 0)) * 0.6;
        $scores[(int)$p['id']] = round($score, 3);
    }
    // Requête de plusieurs mots : on écarte les articles qui n'en couvrent qu'une petite partie
    if (count($tokens) >= 2 && $coverages) {
        $maxCov = max($coverages);
        $scores = array_filter($scores, fn($s, $id) => $coverages[$id] >= $maxCov * 0.5, ARRAY_FILTER_USE_BOTH);
    }
    arsort($scores);
    // On écarte le bruit très faible
    if ($scores) {
        $top = reset($scores);
        $scores = array_filter($scores, fn($s) => $s >= $top * 0.18);
    }
    return $scores;
}

// ---------------------------------------------------------------- Assistant IA (Claude)

function ai_api_key(): string
{
    return ai_key_info()['key'];
}

/** Clé API utilisée et son origine : paramètres (chiffrée en base), config.php ou variable d'environnement. */
function ai_key_info(): array
{
    static $info = null;
    if ($info !== null) {
        return $info;
    }
    $stored = setting('ai_api_key');
    if ($stored) {
        try {
            $key = decrypt_secret($stored);
            if ($key !== '') {
                return $info = ['key' => $key, 'source' => 'settings'];
            }
        } catch (Throwable $e) {
            error_log('[ai] ' . $e->getMessage());
        }
    }
    if ($k = (string)cfg('anthropic_api_key', '')) {
        return $info = ['key' => $k, 'source' => 'config'];
    }
    if ($k = (string)getenv('ANTHROPIC_API_KEY')) {
        return $info = ['key' => $k, 'source' => 'env'];
    }
    return $info = ['key' => '', 'source' => 'none'];
}

function ai_sdk_installed(): bool
{
    return class_exists(\Anthropic\Client::class);
}

function ai_available(): bool
{
    return setting('ai_enabled', '1') === '1' && ai_api_key() !== '' && ai_sdk_installed();
}

function ai_model(): string
{
    return setting('ai_model') ?: 'claude-opus-5-5';
}

/**
 * Interroge Claude avec la requête et le catalogue du centre.
 * Renvoie ['ids' => int[], 'message' => string, 'related' => string[]] ou null en cas d'indisponibilité.
 */
function ai_search(array $products, string $query): ?array
{
    if (!ai_available() || mb_strlen($query) < 2) {
        return null;
    }
    // Catalogue compact, trié par id pour rester stable (et profiter du cache de prompt).
    $max = max(50, (int)setting('ai_max_products', '1500'));
    usort($products, fn($a, $b) => $a['id'] <=> $b['id']);
    $products = array_slice($products, 0, $max);
    $lines = [];
    $valid = [];
    foreach ($products as $p) {
        $valid[(int)$p['id']] = true;
        $desc = mb_substr(preg_replace('/\s+/', ' ', strip_tags((string)$p['description'])) ?? '', 0, 140);
        $lines[] = implode(' | ', array_filter([
            '#' . $p['id'], $p['name'], $p['category_name'] ?? '', $p['unit'] ?? '',
            $p['keywords'] ? 'mots-clés: ' . $p['keywords'] : '', $desc,
        ], fn($v) => $v !== ''));
    }
    $catalog = implode("\n", $lines);

    $model = ai_model();
    $cacheKey = hash('sha256', $model . '|' . md5($catalog) . '|' . search_normalize($query));
    $cached = val('SELECT response FROM ai_cache WHERE cache_key = ? AND created_at >= ?', [$cacheKey, date('Y-m-d H:i:s', strtotime('-7 days'))]);
    if ($cached) {
        $r = json_decode((string)$cached, true);
        if (is_array($r)) {
            return $r + ['cached' => true];
        }
    }

    $system = "Tu es l'assistant achats d'un groupe de centres de santé (médecins, kinésithérapeutes, secrétaires, infirmiers…). "
        . "Un salarié décrit ce dont il a besoin, parfois de façon vague, avec des fautes ou des noms de marque. "
        . "Ta mission : choisir dans le catalogue ci-dessous les articles qui répondent le mieux à son besoin.\n"
        . "Règles :\n"
        . "- Ne propose QUE des identifiants présents dans le catalogue (format #id).\n"
        . "- Classe du plus pertinent au moins pertinent, 12 articles au maximum ; inclus les alternatives utiles (autre taille, autre marque).\n"
        . "- Si rien ne correspond, renvoie une liste vide et explique-le simplement.\n"
        . "- « message » : une phrase courte et bienveillante en français qui aide le salarié à choisir (ex. préciser la taille, le conditionnement).\n"
        . "- « related » : jusqu'à 3 recherches complémentaires courtes que le salarié pourrait vouloir faire.\n\n"
        . "CATALOGUE (#id | nom | catégorie | conditionnement | mots-clés | description) :\n" . $catalog;

    try {
        $client = new \Anthropic\Client(apiKey: ai_api_key(), baseUrl: cfg('anthropic_base_url') ?: null);
        $message = $client->beta->messages->create(
            model: $model,
            maxTokens: 8000,
            system: [
                ['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']],
            ],
            messages: [
                ['role' => 'user', 'content' => 'Besoin du salarié : ' . mb_substr($query, 0, 500)],
            ],
            outputConfig: [
                'effort' => 'low',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            'message'     => ['type' => 'string'],
                            'related'     => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['product_ids', 'message', 'related'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            // Si le modèle décline une requête, l'API bascule automatiquement sur un modèle de repli.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => 45, 'maxRetries' => 1],
        );
    } catch (\Anthropic\Core\Exceptions\RateLimitException $e) {
        error_log('[ai_search] limite de débit atteinte : ' . $e->getMessage());
        return null;
    } catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
        error_log('[ai_search] clé API invalide : ' . $e->getMessage());
        return null;
    } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
        error_log('[ai_search] erreur API : ' . $e->getMessage());
        return null;
    } catch (\Anthropic\Core\Exceptions\APIConnectionException $e) {
        error_log('[ai_search] connexion impossible : ' . $e->getMessage());
        return null;
    } catch (\Throwable $e) {
        error_log('[ai_search] ' . get_class($e) . ' : ' . $e->getMessage());
        return null;
    }

    if ($message->stopReason === 'refusal') {
        return null;
    }
    $data = null;
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            break;
        }
    }
    if (!is_array($data)) {
        return null;
    }
    $ids = [];
    foreach ((array)($data['product_ids'] ?? []) as $id) {
        $id = (int)$id;
        if (isset($valid[$id]) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    $result = [
        'ids'     => array_slice($ids, 0, 12),
        'message' => mb_substr((string)($data['message'] ?? ''), 0, 400),
        'related' => array_slice(array_map(fn($s) => mb_substr((string)$s, 0, 60), (array)($data['related'] ?? [])), 0, 3),
    ];
    q('DELETE FROM ai_cache WHERE cache_key = ?', [$cacheKey]);
    insert('ai_cache', ['cache_key' => $cacheKey, 'response' => json_encode($result, JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
    return $result;
}
