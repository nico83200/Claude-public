<?php
declare(strict_types=1);

/**
 * Import assisté d'une liste d'articles (CSV, Excel, OpenDocument).
 * 1. lecture du fichier ; 2. correspondance des colonnes (IA, ou règles locales) ;
 * 3. correspondance des fournisseurs et catégories (IA) ; 4. aperçu ligne à ligne ; 5. import.
 */

/** Colonnes de l'export CSV du catalogue (également reconnues à l'import). */
const IMPORT_COLUMNS = ['fournisseur', 'reference', 'designation', 'description', 'categorie', 'conditionnement', 'prix_catalogue', 'prix_negocie', 'mots_cles', 'tva', 'code_barre', 'groupe_equivalence'];

/** Champs du catalogue : libellé et intitulés de colonnes courants (normalisés). */
const IMPORT_FIELDS = [
    'name'             => ['Désignation *', ['designation', 'libelle', 'nom', 'article', 'produit', 'intitule', 'description courte', 'nom produit', 'libelle article', 'product', 'name']],
    'reference'        => ['Référence fournisseur', ['reference', 'ref', 'code article', 'ref fournisseur', 'sku', 'code produit', 'ref article', 'code']],
    'barcode'          => ['Code-barres (EAN)', ['ean', 'gtin', 'code barre', 'codebarre', 'ean13', 'code ean', 'barcode', 'acl', 'cip']],
    'description'      => ['Description', ['description', 'descriptif', 'detail', 'details', 'caracteristiques', 'description longue']],
    'unit'             => ['Conditionnement', ['conditionnement', 'unite', 'colisage', 'uv', 'unite de vente', 'packaging', 'contenance', 'format']],
    'catalog_price'    => ['Prix catalogue HT', ['prix catalogue', 'prix public', 'tarif', 'prix unitaire', 'pu ht', 'prix ht', 'prix', 'prix de vente', 'tarif public', 'prix brut']],
    'negotiated_price' => ['Prix négocié HT', ['prix negocie', 'prix net', 'net', 'prix remise', 'prix client', 'votre prix', 'prix contrat', 'prix nego']],
    'vat_rate'         => ['TVA (%)', ['tva', 'taux tva', 'taux de tva', 'vat']],
    'category'         => ['Catégorie', ['categorie', 'famille', 'rayon', 'sous famille', 'gamme', 'univers', 'category']],
    'supplier'         => ['Fournisseur', ['fournisseur', 'distributeur', 'vendeur', 'supplier']],
    'brand'            => ['Marque (ajoutée aux mots-clés)', ['marque', 'fabricant', 'laboratoire', 'brand']],
    'keywords'         => ['Mots-clés', ['mots cles', 'mot cle', 'tags', 'keywords']],
    'min_qty'          => ['Quantité minimale', ['quantite minimale', 'qte min', 'minimum de commande', 'mini', 'qmin']],
    'compare_group'    => ['Groupe d\'équivalence', ['groupe equivalence', 'groupe d equivalence', 'equivalence']],
];

function import_dir(): string
{
    $d = ROOT . '/storage/imports';
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
    // Fichiers de travail de plus d'un jour supprimés
    foreach (glob($d . '/*.json') ?: [] as $f) {
        if (filemtime($f) < time() - 86400) {
            @unlink($f);
        }
    }
    return $d;
}

function import_save(array $state): void
{
    file_put_contents(import_dir() . '/' . $state['token'] . '.json', json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function import_load(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
        return null;
    }
    $f = import_dir() . "/$token.json";
    $state = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($state) && (int)($state['uid'] ?? 0) === (int)(user()['id'] ?? -1) ? $state : null;
}

/** Lit le fichier envoyé et prépare l'import. */
function import_start(string $tmpPath, string $fileName, int $uid): array
{
    $rows = spreadsheet_read($tmpPath, $fileName);
    if (count($rows) < 2) {
        throw new RuntimeException('Le fichier ne contient pas de lignes exploitables (il faut une ligne d\'en-têtes et au moins un article).');
    }
    $h = sheet_header_row($rows);
    $headers = array_map(fn($v, $i) => $v !== '' ? $v : 'Colonne ' . ($i + 1), $rows[$h], array_keys($rows[$h]));
    $data = array_slice($rows, $h + 1, 5000);
    // Lignes de total / sous-titres sans contenu exploitable : ignorées plus tard (pas de désignation)
    return [
        'token' => bin2hex(random_bytes(12)), 'uid' => $uid, 'file' => mb_substr($fileName, 0, 120), 'created' => now(),
        'headers' => $headers, 'rows' => $data, 'skipped_top' => $h, 'truncated' => count($rows) - $h - 1 > 5000,
        'mapping' => [], 'mapping_source' => 'local', 'mapping_notes' => '', 'prices_ttc' => false,
        'default_supplier' => 0, 'supplier_map' => [], 'category_map' => [], 'row_categories' => [], 'ai_error' => '',
    ];
}

function import_norm(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', ' ', search_normalize($s)) ?? '');
}

/** Correspondance des colonnes par règles (intitulés usuels). */
function import_guess_mapping(array $headers): array
{
    $map = [];
    $used = [];
    $norm = array_map('import_norm', $headers);
    // Correspondances exactes d'abord, puis partielles
    foreach ([true, false] as $exact) {
        foreach (IMPORT_FIELDS as $field => [, $syn]) {
            if (isset($map[$field])) {
                continue;
            }
            foreach ($norm as $i => $h) {
                if (isset($used[$i]) || $h === '') {
                    continue;
                }
                foreach ($syn as $s) {
                    if ($exact ? $h === $s : (str_contains(" $h ", " $s ") || ($field !== 'name' && str_starts_with($h, $s)))) {
                        // « prix » seul ne doit pas capter « prix net »
                        if (!$exact && $field === 'catalog_price' && preg_match('/net|nego|remis|client|contrat/', $h)) {
                            continue;
                        }
                        $map[$field] = $i;
                        $used[$i] = true;
                        continue 3;
                    }
                }
            }
        }
    }
    return $map;
}

/** Correspondance des colonnes par l'IA (à partir des en-têtes et d'un échantillon de lignes). */
function import_ai_mapping(array $headers, array $rows): ?array
{
    $fields = [];
    foreach (IMPORT_FIELDS as $k => [$label]) {
        $fields[] = "- $k : " . str_replace(' *', ' (obligatoire)', $label);
    }
    $sample = [];
    foreach (array_slice($rows, 0, 8) as $r) {
        $sample[] = implode(' | ', array_map(fn($v) => mb_substr($v, 0, 60), $r));
    }
    $cols = [];
    foreach ($headers as $i => $h) {
        $cols[] = "$i: $h";
    }
    $system = "Tu aides le service achats d'un groupe de centres de santé à importer le catalogue d'un fournisseur. "
        . "On te donne les colonnes d'un fichier tableur et quelques lignes. Associe chaque champ du catalogue à l'index de la colonne qui le contient, ou null si aucune colonne ne convient. "
        . "Une colonne ne sert qu'à un champ. Règles :\n"
        . "- name : la désignation lisible de l'article (pas un code).\n"
        . "- catalog_price : le prix public / tarif de base HT ; negotiated_price : le prix net / remisé propre au client, s'il existe une colonne distincte.\n"
        . "- Si les prix semblent TTC (intitulé « TTC »), indique prices_ttc = true.\n"
        . "- barcode : EAN/GTIN (8 à 14 chiffres) ; reference : la référence ou le code article du fournisseur.\n"
        . "- notes : une phrase courte en français pour signaler une ambiguïté utile à l'acheteur (ou chaîne vide).\n\n"
        . "Champs du catalogue :\n" . implode("\n", $fields);
    $props = [];
    foreach (array_keys(IMPORT_FIELDS) as $k) {
        $props[$k] = ['anyOf' => [['type' => 'integer'], ['type' => 'null']]];
    }
    $data = ai_json($system, "Colonnes :\n" . implode("\n", $cols) . "\n\nPremières lignes :\n" . implode("\n", $sample), [
        'type' => 'object',
        'properties' => [
            'columns' => ['type' => 'object', 'properties' => $props, 'required' => array_keys(IMPORT_FIELDS), 'additionalProperties' => false],
            'prices_ttc' => ['type' => 'boolean'],
            'notes' => ['type' => 'string'],
        ],
        'required' => ['columns', 'prices_ttc', 'notes'],
        'additionalProperties' => false,
    ], 4000);
    if (!$data) {
        return null;
    }
    $map = [];
    $used = [];
    foreach ((array)$data['columns'] as $field => $i) {
        if (isset(IMPORT_FIELDS[$field]) && is_int($i) && isset($headers[$i]) && !isset($used[$i])) {
            $map[$field] = $i;
            $used[$i] = true;
        }
    }
    return ['mapping' => $map, 'prices_ttc' => (bool)$data['prices_ttc'], 'notes' => mb_substr((string)$data['notes'], 0, 300)];
}

/** Valeurs distinctes non vides d'une colonne. */
function import_distinct(array $state, string $field): array
{
    $i = $state['mapping'][$field] ?? null;
    if ($i === null) {
        return [];
    }
    $vals = [];
    foreach ($state['rows'] as $r) {
        $v = trim((string)($r[$i] ?? ''));
        if ($v !== '') {
            $vals[$v] = true;
        }
    }
    return array_slice(array_keys($vals), 0, 300);
}

/**
 * Rapproche les fournisseurs et catégories du fichier de ceux de l'application,
 * et propose une catégorie pour chaque article qui n'en a pas (IA si disponible).
 */
function import_match_values(array &$state, bool $useAi): void
{
    $suppliers = all('SELECT id, name FROM suppliers ORDER BY name');
    $categories = all('SELECT id, name FROM categories ORDER BY position, name');
    $supVals = import_distinct($state, 'supplier');
    $catVals = import_distinct($state, 'category');

    // 1. Règles locales : même nom (sans accents ni ponctuation)
    $local = function (array $values, array $refs): array {
        $idx = [];
        foreach ($refs as $r) {
            $idx[import_norm($r['name'])] = (int)$r['id'];
        }
        $out = [];
        foreach ($values as $v) {
            $out[$v] = $idx[import_norm($v)] ?? 0;
        }
        return $out;
    };
    $state['supplier_map'] = $local($supVals, $suppliers);
    $state['category_map'] = $local($catVals, $categories);
    $state['row_categories'] = [];
    $state['ai_error'] = '';
    if (!$useAi || !ai_available()) {
        return;
    }
    @set_time_limit(300);

    // 2. IA : rapprochement des valeurs restantes (« Hygiène / Désinfection » → « Hygiène & désinfection »)
    $unSup = array_keys(array_filter($state['supplier_map'], fn($id) => $id === 0));
    $unCat = array_keys(array_filter($state['category_map'], fn($id) => $id === 0));
    if ($unSup || $unCat) {
        $list = fn(array $refs) => implode("\n", array_map(fn($r) => '#' . $r['id'] . ' ' . $r['name'], $refs));
        $system = "Tu rapproches les valeurs d'un fichier fournisseur des fournisseurs et catégories existants d'un logiciel d'achats de centres de santé. "
            . "Pour chaque valeur, donne l'identifiant existant qui désigne la même chose (synonyme, abréviation, faute, pluriel, sous-famille évidente), ou 0 s'il n'y en a pas : il sera alors créé.\n\n"
            . "FOURNISSEURS EXISTANTS :\n" . ($list($suppliers) ?: '(aucun)') . "\n\nCATÉGORIES EXISTANTES :\n" . $list($categories);
        $item = ['type' => 'object', 'properties' => ['value' => ['type' => 'string'], 'id' => ['type' => 'integer']], 'required' => ['value', 'id'], 'additionalProperties' => false];
        $r = ai_json($system, "Fournisseurs du fichier :\n" . (implode("\n", $unSup) ?: '(aucun)') . "\n\nCatégories du fichier :\n" . (implode("\n", $unCat) ?: '(aucune)'), [
            'type' => 'object',
            'properties' => ['suppliers' => ['type' => 'array', 'items' => $item], 'categories' => ['type' => 'array', 'items' => $item]],
            'required' => ['suppliers', 'categories'], 'additionalProperties' => false,
        ], 8000);
        if ($r === null) {
            $state['ai_error'] = ai_last_error();
            return;
        }
        $supIds = array_column($suppliers, 'id');
        $catIds = array_column($categories, 'id');
        foreach ($r['suppliers'] as $m) {
            if (array_key_exists($m['value'], $state['supplier_map']) && in_array($m['id'], $supIds, false)) {
                $state['supplier_map'][$m['value']] = (int)$m['id'];
            }
        }
        foreach ($r['categories'] as $m) {
            if (array_key_exists($m['value'], $state['category_map']) && in_array($m['id'], $catIds, false)) {
                $state['category_map'][$m['value']] = (int)$m['id'];
            }
        }
    }

    // 3. IA : catégorie proposée pour les articles sans catégorie reconnue (par lots, 600 articles au plus)
    $nameCol = $state['mapping']['name'] ?? null;
    if ($nameCol === null) {
        return;
    }
    $catCol = $state['mapping']['category'] ?? null;
    $todo = [];
    foreach ($state['rows'] as $i => $row) {
        $name = trim((string)($row[$nameCol] ?? ''));
        $cv = $catCol !== null ? trim((string)($row[$catCol] ?? '')) : '';
        if ($name !== '' && ($cv === '' || ($state['category_map'][$cv] ?? 0) === 0)) {
            $desc = isset($state['mapping']['description']) ? mb_substr((string)($row[$state['mapping']['description']] ?? ''), 0, 80) : '';
            $todo[$i] = mb_substr($name, 0, 120) . ($cv !== '' ? " [famille fichier : $cv]" : '') . ($desc !== '' ? " — $desc" : '');
        }
    }
    if (!$todo) {
        return;
    }
    $system = "Tu classes des articles achetés par des centres de santé (consommables médicaux, hygiène, bureau, kinésithérapie, entretien…) dans les catégories existantes. "
        . "Pour chaque article (identifié par son numéro de ligne), donne l'identifiant de la catégorie la plus adaptée, ou 0 si aucune ne convient vraiment.\n\n"
        . "CATÉGORIES :\n" . implode("\n", array_map(fn($c) => '#' . $c['id'] . ' ' . $c['name'], $categories));
    $catIds = array_map('intval', array_column($categories, 'id'));
    foreach (array_chunk(array_slice($todo, 0, 600, true), 150, true) as $batch) {
        $lines = [];
        foreach ($batch as $i => $t) {
            $lines[] = "$i: $t";
        }
        $r = ai_json($system, implode("\n", $lines), [
            'type' => 'object',
            'properties' => ['items' => ['type' => 'array', 'items' => [
                'type' => 'object', 'properties' => ['line' => ['type' => 'integer'], 'category_id' => ['type' => 'integer']],
                'required' => ['line', 'category_id'], 'additionalProperties' => false,
            ]]],
            'required' => ['items'], 'additionalProperties' => false,
        ], 12000, 120);
        if ($r === null) {
            $state['ai_error'] = ai_last_error();
            break;
        }
        foreach ($r['items'] as $m) {
            if (isset($batch[$m['line']]) && in_array((int)$m['category_id'], $catIds, true)) {
                $state['row_categories'][(string)$m['line']] = (int)$m['category_id'];
            }
        }
    }
}

/**
 * Transforme les lignes du fichier en articles prêts à importer, avec leur statut :
 * 'new' (création), 'update' (article existant : même fournisseur + référence, même code-barres, ou même désignation) ou 'error'.
 */
function import_prepare(array $state): array
{
    $m = $state['mapping'];
    $get = fn(array $r, string $f) => isset($m[$f]) ? trim((string)($r[$m[$f]] ?? '')) : '';
    $catNames = array_column(all('SELECT id, name FROM categories'), 'name', 'id');
    $supNames = array_column(all('SELECT id, name FROM suppliers'), 'name', 'id');
    $byRef = $byBarcode = $byName = [];
    foreach (all('SELECT id, supplier_id, reference, barcode, name FROM products') as $p) {
        if ($p['reference'] !== null && $p['reference'] !== '') {
            $byRef[$p['supplier_id'] . '|' . mb_strtolower($p['reference'])] = (int)$p['id'];
        }
        if ($p['barcode']) {
            $byBarcode[$p['barcode']] = (int)$p['id'];
        }
        $byName[$p['supplier_id'] . '|' . import_norm($p['name'])] = (int)$p['id'];
    }
    $out = [];
    $seen = [];
    foreach ($state['rows'] as $i => $r) {
        $name = $get($r, 'name');
        if ($name === '' || preg_match('/^(sous[\s-]?)?total\b|^montant\b/iu', $name)) {
            continue;   // ligne vide, de titre ou de total
        }
        $supText = $get($r, 'supplier');
        $supId = $supText !== '' ? (int)($state['supplier_map'][$supText] ?? 0) : (int)$state['default_supplier'];
        $catText = $get($r, 'category');
        $catId = (int)($state['row_overrides'][$i] ?? 0) ?: (($catText !== '' ? (int)($state['category_map'][$catText] ?? 0) : 0) ?: (int)($state['row_categories'][(string)$i] ?? 0));
        $vat = parse_number($get($r, 'vat_rate'));
        if ($vat !== null && $vat > 0 && $vat < 1) {
            $vat *= 100;   // 0,2 → 20 %
        }
        $vat = $vat ?? 20.0;
        $ttc = !empty($state['prices_ttc']);
        $price = fn(?float $p) => $p === null ? null : round($ttc ? $p / (1 + $vat / 100) : $p, 2);
        $catalog = $price(parse_number($get($r, 'catalog_price')));
        $nego = $price(parse_number($get($r, 'negotiated_price')));
        if ($catalog === null && $nego !== null) {
            $catalog = $nego;
        }
        if ($nego !== null && $catalog !== null && $nego >= $catalog) {
            $nego = null;   // pas de remise réelle
        }
        $barcode = preg_replace('/\D+/', '', $get($r, 'barcode')) ?: null;
        if ($barcode !== null && (strlen($barcode) < 8 || strlen($barcode) > 14)) {
            $barcode = null;
        }
        $ref = mb_substr($get($r, 'reference'), 0, 80) ?: null;
        $keywords = trim(implode(' ', array_filter([$get($r, 'keywords'), $get($r, 'brand')])));
        $row = [
            'line' => $i, 'name' => mb_substr($name, 0, 200), 'reference' => $ref, 'barcode' => $barcode,
            'description' => $get($r, 'description') ?: null, 'unit' => mb_substr($get($r, 'unit'), 0, 80) ?: null,
            'catalog_price' => $catalog, 'negotiated_price' => $nego, 'vat_rate' => round($vat, 2),
            'keywords' => mb_substr($keywords, 0, 500) ?: null, 'min_qty' => max(1, (int)parse_number($get($r, 'min_qty'))),
            'compare_group' => mb_substr($get($r, 'compare_group'), 0, 80) ?: null,
            'supplier_id' => $supId, 'supplier_text' => $supText, 'supplier_name' => $supId ? ($supNames[$supId] ?? '') : ($supText !== '' ? $supText . ' (nouveau)' : ''),
            'category_id' => $catId, 'category_text' => $catText, 'category_name' => $catId ? ($catNames[$catId] ?? '') : '',
            'category_ai' => $catId && $catText === '' && isset($state['row_categories'][(string)$i]),
            'status' => 'new', 'existing_id' => null, 'error' => null,
        ];
        if (!$supId && $supText === '') {
            [$row['status'], $row['error']] = ['error', 'fournisseur manquant (choisissez un fournisseur par défaut)'];
        } elseif ($catalog === null) {
            [$row['status'], $row['error']] = ['error', 'prix illisible ou absent'];
        } else {
            $existing = null;
            if ($supId) {
                $existing = ($ref ? ($byRef[$supId . '|' . mb_strtolower($ref)] ?? null) : null)
                    ?? ($barcode ? ($byBarcode[$barcode] ?? null) : null)
                    ?? ($byName[$supId . '|' . import_norm($name)] ?? null);
            }
            if ($existing) {
                [$row['status'], $row['existing_id']] = ['update', $existing];
            }
            $key = ($supId ?: $supText) . '|' . ($ref ?: import_norm($name));
            if (isset($seen[$key])) {
                [$row['status'], $row['error']] = ['error', 'doublon dans le fichier (ligne ' . ($seen[$key] + $state['skipped_top'] + 2) . ')'];
            }
            $seen[$key] = $i;
        }
        $out[] = $row;
    }
    return $out;
}

/** Applique l'import. $selected : lignes cochées ; $updateExisting : mettre à jour les articles déjà présents. */
function import_apply(array $state, array $selected, bool $updateExisting): array
{
    $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'suppliers' => 0, 'categories' => 0];
    $rows = import_prepare($state);
    tx(function () use ($rows, $state, $selected, $updateExisting, &$report) {
        $newSup = [];
        $newCat = [];
        $catPos = (int)val('SELECT COALESCE(MAX(position), 0) FROM categories');
        foreach ($rows as $r) {
            if ($r['status'] === 'error' || !isset($selected[$r['line']]) || ($r['status'] === 'update' && !$updateExisting)) {
                $report['skipped']++;
                continue;
            }
            $supId = $r['supplier_id'];
            if (!$supId) {
                $k = import_norm($r['supplier_text']);
                if (!isset($newSup[$k])) {
                    $newSup[$k] = insert('suppliers', ['name' => mb_substr($r['supplier_text'], 0, 150), 'all_centers' => 1, 'active' => 1,
                        'color' => palette()[($report['suppliers'] + 3) % 12], 'created_at' => now()]);
                    $report['suppliers']++;
                }
                $supId = $newSup[$k];
            }
            $catId = $r['category_id'] ?: null;
            if (!$catId && $r['category_text'] !== '' && !empty($state['create_categories'])) {
                $k = import_norm($r['category_text']);
                if (!isset($newCat[$k])) {
                    $newCat[$k] = insert('categories', ['name' => mb_substr($r['category_text'], 0, 100), 'icon' => 'box',
                        'color' => palette()[($catPos + $report['categories']) % 12], 'position' => $catPos + $report['categories'] + 1]);
                    $report['categories']++;
                }
                $catId = $newCat[$k];
            }
            $data = [
                'supplier_id' => $supId, 'category_id' => $catId, 'reference' => $r['reference'], 'name' => $r['name'],
                'unit' => $r['unit'], 'catalog_price' => $r['catalog_price'], 'negotiated_price' => $r['negotiated_price'],
                'vat_rate' => $r['vat_rate'], 'min_qty' => $r['min_qty'], 'updated_at' => now(),
            ];
            // Champs facultatifs : on n'efface pas une information existante par une cellule vide
            foreach (['barcode', 'description', 'keywords', 'compare_group'] as $f) {
                if ($r[$f] !== null) {
                    $data[$f] = $r[$f];
                }
            }
            if ($r['status'] === 'update') {
                if (!$catId) {
                    unset($data['category_id']);
                }
                update('products', $data, 'id = ?', [$r['existing_id']]);
                price_record((int)$r['existing_id'], (float)$data['catalog_price'], $data['negotiated_price'], 'Import fichier');
                $report['updated']++;
            } else {
                $id = insert('products', $data + ['active' => 1, 'created_at' => now()]);
                price_record($id, (float)$data['catalog_price'], $data['negotiated_price'], 'Import fichier');
                $report['created']++;
            }
        }
    });
    q('DELETE FROM ai_cache');
    return $report;
}
