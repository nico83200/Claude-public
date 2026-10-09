<?php
declare(strict_types=1);

/**
 * Fonctions de la version 1.3 : audit, sécurité de connexion, mot de passe oublié,
 * historique des prix, équivalences fournisseurs, listes types, validation par le
 * responsable de centre, commandes groupées et rapprochement des factures.
 */

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

// ---------------------------------------------------------------- Journal d'audit

function audit(string $action, ?string $entity = null, ?int $entityId = null, string|array|null $details = null): void
{
    try {
        insert('audit_log', [
            'user_id' => user()['id'] ?? null, 'action' => mb_substr($action, 0, 60), 'entity' => $entity, 'entity_id' => $entityId,
            'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
            'ip' => client_ip(), 'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('[audit] ' . $e->getMessage());
    }
}

/** Décrit les différences entre deux versions d'un enregistrement (pour l'audit). */
function audit_diff(array $before, array $after, array $labels): ?string
{
    $out = [];
    foreach ($labels as $k => $label) {
        $a = $before[$k] ?? null;
        $b = $after[$k] ?? null;
        if ((string)$a !== (string)$b && !(is_numeric($a) && is_numeric($b) && abs((float)$a - (float)$b) < 0.001)) {
            $out[] = $label . ' : ' . ($a === null || $a === '' ? '—' : $a) . ' → ' . ($b === null || $b === '' ? '—' : $b);
        }
    }
    return $out ? implode(' · ', $out) : null;
}

// ---------------------------------------------------------------- Protection de la connexion

const LOGIN_MAX_PER_EMAIL = 5;   // échecs tolérés par compte sur 15 minutes
const LOGIN_MAX_PER_IP = 20;     // échecs tolérés par adresse IP sur 15 minutes

function login_blocked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    $byEmail = (int)val('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at >= ?', [$email, $since]);
    $byIp = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at >= ?', [client_ip(), $since]);
    return $byEmail >= LOGIN_MAX_PER_EMAIL || $byIp >= LOGIN_MAX_PER_IP;
}

function login_record(string $email, bool $success): void
{
    insert('login_attempts', ['email' => mb_substr($email, 0, 190), 'ip' => client_ip(), 'success' => $success ? 1 : 0, 'created_at' => now()]);
    if ($success) {
        q('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$email]);
    }
}

// ---------------------------------------------------------------- Mot de passe oublié

function password_reset_create(array $u): string
{
    $token = bin2hex(random_bytes(32));
    q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $u['id']]);
    insert('password_resets', [
        'user_id' => $u['id'], 'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'created_at' => now(),
    ]);
    return $token;
}

function password_reset_find(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return one("SELECT pr.*, u.email, u.first_name FROM password_resets pr JOIN users u ON u.id = pr.user_id
                WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at >= ? AND u.status = 'active'",
        [hash('sha256', $token), now()]);
}

// ---------------------------------------------------------------- Historique des prix

function price_record(int $productId, float $catalog, ?float $negotiated, string $source, bool $notify = true): void
{
    $last = one('SELECT * FROM price_history WHERE product_id = ? ORDER BY created_at DESC, id DESC LIMIT 1', [$productId]);
    $same = $last && abs((float)$last['catalog_price'] - $catalog) < 0.001
        && (($last['negotiated_price'] === null && $negotiated === null)
            || ($last['negotiated_price'] !== null && $negotiated !== null && abs((float)$last['negotiated_price'] - $negotiated) < 0.001));
    if ($same) {
        return;
    }
    insert('price_history', [
        'product_id' => $productId, 'catalog_price' => $catalog, 'negotiated_price' => $negotiated,
        'source' => $source, 'user_id' => user()['id'] ?? null, 'created_at' => now(),
    ]);
    if ($last) {
        $old = ($last['negotiated_price'] !== null && (float)$last['negotiated_price'] > 0) ? (float)$last['negotiated_price'] : (float)$last['catalog_price'];
        $new = ($negotiated !== null && $negotiated > 0) ? $negotiated : $catalog;
        if ($notify && $old > 0 && $new > $old * 1.005) {
            $p = one('SELECT p.name, s.name AS supplier_name FROM products p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [$productId]);
            notify(admin_ids(), 'price_increase', 'Hausse de prix : ' . $p['name'],
                $p['supplier_name'] . ' : ' . money($old) . ' → ' . money($new) . ' (+' . round(($new / $old - 1) * 100, 1) . ' %).',
                url('admin/product', ['id' => $productId]));
        }
    }
}

/** Seuil (en %) au-delà duquel une hausse de prix est signalée lors d'un import de tarifs. */
function price_alert_pct(): float
{
    return max(0.0, (float)setting('price_alert_pct', '5'));
}

/** Hausses de prix récentes (prix appliqué supérieur au précédent). */
function recent_price_increases(int $days = 90, int $limit = 8): array
{
    $rows = all('SELECT h.*, p.name, s.name AS supplier_name FROM price_history h JOIN products p ON p.id = h.product_id
                 JOIN suppliers s ON s.id = p.supplier_id WHERE h.created_at >= ? ORDER BY h.created_at DESC', [date('Y-m-d H:i:s', strtotime("-$days days"))]);
    $out = [];
    foreach ($rows as $h) {
        if (isset($out[$h['product_id']])) {
            continue;
        }
        $prev = one('SELECT * FROM price_history WHERE product_id = ? AND (created_at < ? OR (created_at = ? AND id < ?)) ORDER BY created_at DESC, id DESC LIMIT 1',
            [$h['product_id'], $h['created_at'], $h['created_at'], $h['id']]);
        if (!$prev) {
            continue;
        }
        $eff = fn($r) => ($r['negotiated_price'] !== null && (float)$r['negotiated_price'] > 0) ? (float)$r['negotiated_price'] : (float)$r['catalog_price'];
        if ($eff($h) > $eff($prev) * 1.005) {
            $out[$h['product_id']] = $h + ['old' => $eff($prev), 'new' => $eff($h), 'pct' => round(($eff($h) / $eff($prev) - 1) * 100, 1)];
        }
        if (count($out) >= $limit) {
            break;
        }
    }
    return array_values($out);
}

// ---------------------------------------------------------------- Équivalences entre fournisseurs

/** Articles équivalents (même groupe de comparaison ou même code-barres). */
function product_equivalents(array $p, ?int $centerId = null): array
{
    $conds = [];
    $params = [];
    if (!empty($p['compare_group'])) {
        $conds[] = 'p.compare_group = ?';
        $params[] = $p['compare_group'];
    }
    if (!empty($p['barcode'])) {
        $conds[] = 'p.barcode = ?';
        $params[] = $p['barcode'];
    }
    if (!$conds) {
        return [];
    }
    $sql = 'SELECT p.*, s.name AS supplier_name, s.color AS supplier_color FROM products p JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.active = 1 AND p.id <> ? AND (' . implode(' OR ', $conds) . ')';
    array_unshift($params, $p['id']);
    if ($centerId) {
        $sql .= ' AND ' . supplier_visible_sql('s');
        $params[] = $centerId;
    }
    return all($sql, $params);
}

/** Équivalent le moins cher (ou null si l'article est déjà le meilleur prix). */
function cheaper_equivalent(array $p, ?int $centerId = null): ?array
{
    $best = null;
    $ref = effective_price($p);
    foreach (product_equivalents($p, $centerId) as $e) {
        $price = effective_price($e);
        if ($price > 0 && $price < $ref - 0.004 && (!$best || $price < effective_price($best))) {
            $best = $e;
        }
    }
    return $best ? $best + ['saving' => $ref - effective_price($best)] : null;
}

// ---------------------------------------------------------------- Listes types

function kits_for(array $u, int $centerId): array
{
    $kits = all('SELECT k.*, (SELECT COUNT(*) FROM kit_items ki WHERE ki.kit_id = k.id) AS nb, u.first_name, u.last_name
                 FROM kits k LEFT JOIN users u ON u.id = k.user_id
                 WHERE (k.shared = 1 AND (k.center_id IS NULL OR k.center_id = ?)) OR k.user_id = ?
                 ORDER BY k.shared DESC, k.name', [$centerId, $u['id']]);
    return $kits;
}

function kit_items(int $kitId, int $centerId): array
{
    return all('SELECT ki.qty, p.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
                FROM kit_items ki JOIN products p ON p.id = ki.product_id JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id
                WHERE ki.kit_id = ? AND p.active = 1 AND ' . supplier_visible_sql('s') . ' ORDER BY p.name', [$kitId, $centerId]);
}

function kit_can_edit(array $kit): bool
{
    return is_admin() || (int)$kit['user_id'] === (int)(user()['id'] ?? 0);
}

// ---------------------------------------------------------------- Réapprovisionnement

/** Stocks bas du centre, non couverts par une commande en cours ni par le panier, avec quantité suggérée. */
function reorder_suggestions(int $userId, int $centerId): array
{
    $inCart = array_map('intval', array_column(all('SELECT product_id FROM cart_items WHERE user_id = ? AND center_id = ?', [$userId, $centerId]), 'product_id'));
    $pendingReq = array_map('intval', array_column(all("SELECT DISTINCT product_id FROM request_lines WHERE center_id = ? AND status IN ('pending','awaiting')", [$centerId]), 'product_id'));
    $out = [];
    foreach (stock_list($centerId, 'low') as $s) {
        $pid = (int)$s['product_id'];
        if (in_array($pid, $inCart, true) || in_array($pid, $pendingReq, true) || !(int)$s['active']) {
            continue;
        }
        $target = max((int)$s['alert_qty'] * 2, (int)$s['alert_qty'] + 1);
        $need = $target - (int)$s['qty'] - (int)$s['on_order'];
        if ($need > 0) {
            $out[] = $s + ['suggested' => $need];
        }
    }
    return $out;
}

// ---------------------------------------------------------------- Responsable de centre et validation

function is_manager(?array $u = null): bool
{
    $u ??= user();
    return ($u['role'] ?? '') === 'manager';
}

/** Responsables actifs d'un centre (hors demandeur). */
function center_manager_ids(int $centerId, int $exceptUserId = 0): array
{
    return array_map('intval', array_column(all("SELECT u.id FROM users u JOIN user_centers uc ON uc.user_id = u.id
        WHERE uc.center_id = ? AND u.role = 'manager' AND u.status = 'active' AND u.id <> ?", [$centerId, $exceptUserId]), 'id'));
}

/** La demande doit-elle être validée par un responsable avant le service achats ? */
function request_needs_approval(int $centerId, int $userId, float $total): bool
{
    $threshold = (float)str_replace(',', '.', (string)setting('approval_threshold', '0'));
    if ($threshold <= 0 || $total <= $threshold) {
        return false;
    }
    $u = one('SELECT role FROM users WHERE id = ?', [$userId]);
    if (in_array($u['role'] ?? '', ['manager', ...PURCHASING_ROLES], true)) {
        return false;
    }
    return (bool)center_manager_ids($centerId, $userId);
}

/** Centres dont l'utilisateur valide les demandes. */
function approval_center_ids(): array
{
    if (is_admin()) {
        return array_map('intval', array_column(all('SELECT id FROM centers WHERE active = 1'), 'id'));
    }
    if (is_manager()) {
        return array_map('intval', array_column(user_centers(), 'id'));
    }
    return [];
}

function approvals_pending_count(): int
{
    $ids = approval_center_ids();
    if (!$ids) {
        return 0;
    }
    try {
        return (int)val("SELECT COUNT(*) FROM requests WHERE approval_status = 'pending' AND center_id IN " . in_list($ids), $ids);
    } catch (Throwable) {
        return 0;
    }
}

// ---------------------------------------------------------------- Commandes groupées multi-centres

function next_group_ref(): string
{
    $prefix = 'GR-' . date('Y') . '-';
    $last = val('SELECT group_ref FROM purchase_orders WHERE group_ref LIKE ? ORDER BY group_ref DESC LIMIT 1', [$prefix . '%']);
    $n = $last ? (int)substr((string)$last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/**
 * Une seule commande fournisseur pour plusieurs centres : un bon par centre (chaque bon reste
 * attaché à un seul centre) réunis sous une référence de groupe ; le franco s'apprécie sur le total.
 */
function po_create_group(int $supplierId, array $requestLineIds, string $notes = ''): string
{
    return tx(function () use ($supplierId, $requestLineIds, $notes) {
        $lines = $requestLineIds ? all("SELECT id, center_id FROM request_lines WHERE status = 'pending' AND supplier_id = ? AND id IN " . in_list($requestLineIds),
            array_merge([$supplierId], array_map('intval', $requestLineIds))) : [];
        $byCenter = [];
        foreach ($lines as $l) {
            $byCenter[(int)$l['center_id']][] = (int)$l['id'];
        }
        if (count($byCenter) < 1) {
            throw new RuntimeException('Aucune ligne de demande valide sélectionnée.');
        }
        $ref = next_group_ref();
        $poIds = [];
        foreach ($byCenter as $centerId => $ids) {
            $poId = po_create($centerId, $supplierId, $ids, $notes);
            update('purchase_orders', ['group_ref' => $ref], 'id = ?', [$poId]);
            po_log($poId, 'Commande groupée', $ref);
            $poIds[] = $poId;
        }
        group_recompute_shipping($ref);
        return $ref;
    });
}

/** Frais de port d'un groupe : franco calculé sur le total, frais portés par le premier bon. */
function group_recompute_shipping(string $ref): void
{
    $pos = all('SELECT * FROM purchase_orders WHERE group_ref = ? ORDER BY id', [$ref]);
    if (!$pos) {
        return;
    }
    $supplier = one('SELECT * FROM suppliers WHERE id = ?', [$pos[0]['supplier_id']]);
    $total = 0.0;
    foreach ($pos as $po) {
        $total += po_totals((int)$po['id'])['total'];
    }
    $free = (float)$supplier['free_shipping_from'];
    $fee = ($free > 0 && $total >= $free) ? 0.0 : (float)$supplier['shipping_fee'];
    foreach ($pos as $i => $po) {
        update('purchase_orders', ['shipping_fee' => $i === 0 ? $fee : 0], 'id = ?', [$po['id']]);
    }
}

// ---------------------------------------------------------------- Factures

/** Compare la facture au montant reçu (et commandé) et renvoie le statut de rapprochement. */
function invoice_check(array $po): array
{
    $t = po_totals((int)$po['id']);
    $receivedValue = (float)val('SELECT COALESCE(SUM(qty_received * unit_price),0) FROM purchase_order_lines WHERE purchase_order_id = ?', [$po['id']]);
    $expected = round($receivedValue + (float)$po['shipping_fee'], 2);
    $ordered = round($t['total'] + (float)$po['shipping_fee'], 2);
    if ($po['invoice_amount'] === null || $po['invoice_amount'] === '') {
        return ['status' => 'none', 'expected' => $expected, 'ordered' => $ordered, 'diff' => null];
    }
    $tol = (float)str_replace(',', '.', (string)setting('invoice_tolerance', '1'));
    $diff = round((float)$po['invoice_amount'] - $expected, 2);
    return ['status' => abs($diff) <= $tol ? 'ok' : 'ecart', 'expected' => $expected, 'ordered' => $ordered, 'diff' => $diff];
}

function invoice_badge(?string $status): string
{
    return match ($status) {
        'ok' => '<span class="badge badge-green">Facture conforme</span>',
        'ecart' => '<span class="badge badge-red">Écart facture</span>',
        default => '<span class="badge badge-gray">Facture à saisir</span>',
    };
}

function invoices_dir(): string
{
    $d = storage_path('invoices');
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** Enregistre un justificatif de facture (PDF ou image) hors de la zone web. */
/**
 * Lecture d'une facture (PDF ou photo) par l'assistant IA : numéro, date, montants, lignes.
 * Le résultat est comparé au bon de commande ; l'administrateur valide avant enregistrement.
 */
function invoice_ai_read(string $path, array $po): ?array
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $data = base64_encode((string)file_get_contents($path));
    $block = $mime === 'application/pdf'
        ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]]
        : (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => $data]] : null);
    if (!$block) {
        ai_last_error('Format non pris en charge (PDF, JPG, PNG, WebP).');
        return null;
    }
    $lines = all('SELECT label, reference, qty, qty_received, unit_price FROM purchase_order_lines WHERE purchase_order_id = ?', [$po['id']]);
    $expected = implode("\n", array_map(fn($l) => '- ' . $l['label'] . ($l['reference'] ? ' (réf. ' . $l['reference'] . ')' : '') . ' : commandé ' . $l['qty'] . ', reçu ' . $l['qty_received'] . ', ' . number_format((float)$l['unit_price'], 2, ',', '') . ' € HT', $lines));
    $schema = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['is_invoice', 'supplier_name', 'invoice_number', 'invoice_date', 'total_ht', 'total_ttc', 'shipping_ht', 'lines', 'remarks'],
        'properties' => [
            'is_invoice' => ['type' => 'boolean'],
            'supplier_name' => ['type' => 'string'],
            'invoice_number' => ['type' => 'string'],
            'invoice_date' => ['type' => 'string', 'description' => 'AAAA-MM-JJ, vide si absente'],
            'total_ht' => ['type' => ['number', 'null']],
            'total_ttc' => ['type' => ['number', 'null']],
            'shipping_ht' => ['type' => ['number', 'null']],
            'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['label', 'qty', 'unit_price_ht', 'matches_order'],
                'properties' => ['label' => ['type' => 'string'], 'qty' => ['type' => ['number', 'null']], 'unit_price_ht' => ['type' => ['number', 'null']],
                    'matches_order' => ['type' => 'string', 'description' => 'libellé de la ligne du bon correspondante, ou vide si absente du bon']]]],
            'remarks' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'écarts constatés avec le bon (prix, quantités, articles non commandés), en français, phrases courtes'],
        ],
    ];
    return ai_json(
        'Tu lis des factures fournisseurs pour le service achats d\'un groupe de centres de santé. Extrais fidèlement les informations de la facture, '
        . 'montants hors taxes en euros (nombres, point décimal). Compare ensuite avec le bon de commande fourni et liste les écarts concrets. N\'invente rien : laisse vide ou null si illisible.',
        [$block, ['type' => 'text', 'text' => 'Bon de commande ' . $po['po_number'] . ' (fournisseur : ' . ($po['supplier_name'] ?? '') . ', frais de port ' . number_format((float)$po['shipping_fee'], 2, ',', '') . " € HT) :\n" . $expected . "\n\nLis la facture jointe."]],
        $schema, 6000, 90
    );
}

function handle_invoice_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Envoi de la facture impossible (10 Mo maximum).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) {
        throw new RuntimeException('Format de facture non pris en charge (PDF, JPG, PNG).');
    }
    $name = 'facture-' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], invoices_dir() . '/' . $name)) {
        throw new RuntimeException('Impossible d\'enregistrer la facture.');
    }
    return $name;
}

// ---------------------------------------------------------------- Secrets chiffrés (clé API, mot de passe SMTP)

/** Clé de chiffrement propre à l'installation, stockée hors zone web (storage/secret.key). */
function app_secret_key(): string
{
    static $keys = []; // une clé par client (console multi-clients)
    $file = storage_path('secret.key');
    if (isset($keys[$file])) {
        return $keys[$file];
    }
    if (!is_file($file)) {
        @mkdir(dirname($file), 0755, true);
        if (@file_put_contents($file, base64_encode(random_bytes(32)), LOCK_EX) === false) {
            throw new RuntimeException('Impossible de créer storage/secret.key : donnez les droits d\'écriture au dossier storage/ (755).');
        }
        @chmod($file, 0600);
    }
    $key = base64_decode(trim((string)file_get_contents($file)), true) ?: '';
    if (strlen($key) !== 32) {
        throw new RuntimeException('Fichier storage/secret.key invalide.');
    }
    return $keys[$file] = $key;
}

/**
 * Chiffre un secret avec la clé de l'installation : sodium (enc:) si l'extension est présente,
 * sinon OpenSSL AES-256-GCM (enc2:), courant sur les hébergements mutualisés.
 */
function encrypt_secret(string $plain, ?string $method = null): string
{
    $method ??= function_exists('sodium_crypto_secretbox') ? 'sodium' : 'openssl';
    if ($method === 'sodium') {
        $nonce = random_bytes(24);
        return 'enc:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, app_secret_key()));
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('Chiffrement indisponible : activez l\'extension PHP « sodium » ou « openssl » chez l\'hébergeur.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Échec du chiffrement OpenSSL.');
    }
    return 'enc2:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $stored): string
{
    if (!$stored || !preg_match('/^enc2?:/', $stored)) {
        return (string)$stored;
    }
    try {
        if (str_starts_with($stored, 'enc2:')) {
            $raw = base64_decode(substr($stored, 5), true);
            if ($raw === false || strlen($raw) <= 28 || !function_exists('openssl_decrypt')) {
                return '';
            }
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        } else {
            $raw = base64_decode(substr($stored, 4), true);
            if ($raw === false || strlen($raw) <= 24 || !function_exists('sodium_crypto_secretbox_open')) {
                return '';
            }
            $plain = sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), app_secret_key());
        }
    } catch (Throwable $e) {
        error_log('[secret] ' . $e->getMessage());
        return '';
    }
    return $plain === false ? '' : $plain;
}

/** Affichage masqué d'un secret : sk-ant-…a1b2 */
function mask_secret(string $s): string
{
    if ($s === '') {
        return '';
    }
    return mb_substr($s, 0, 7) . '…' . mb_substr($s, -4);
}

// ---------------------------------------------------------------- Identifiants légaux (SIREN, SIRET, FINESS, TVA)

function digits_only(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s) ?? '';
}

/** Clé de Luhn (utilisée par les numéros SIREN et SIRET). */
function luhn_valid(string $digits): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $d = (int)$digits[$i];
        if ($alt) {
            $d *= 2;
            if ($d > 9) {
                $d -= 9;
            }
        }
        $sum += $d;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function siren_valid(string $siren): bool
{
    return (bool)preg_match('/^\d{9}$/', $siren) && luhn_valid($siren);
}

function siret_valid(string $siret): bool
{
    if (!preg_match('/^\d{14}$/', $siret)) {
        return false;
    }
    // Exception La Poste : ses établissements ont une somme de chiffres multiple de 5 (le siège suit la clé de Luhn)
    if (str_starts_with($siret, '356000000') && array_sum(str_split($siret)) % 5 === 0) {
        return true;
    }
    return luhn_valid($siret);
}

/** N° FINESS : 9 caractères, département (dont 2A/2B) puis 7 chiffres. */
function finess_valid(string $f): bool
{
    return (bool)preg_match('/^(\d{2}|2A|2B)\d{7}$/', strtoupper($f));
}

/** N° de TVA intracommunautaire français calculé à partir du SIREN. */
function vat_from_siren(string $siren): string
{
    return 'FR' . str_pad((string)((12 + 3 * ((int)$siren % 97)) % 97), 2, '0', STR_PAD_LEFT) . $siren;
}

/** Coordonnées de facturation d'un centre (adresse de livraison si identique). */
function center_billing(array $c): array
{
    $same = (int)($c['billing_same'] ?? 1) === 1;
    return [
        'name' => ($same ? null : ($c['billing_name'] ?? null)) ?: ($c['legal_name'] ?? null) ?: $c['name'],
        'address' => $same ? trim(($c['address'] ?? '') . ' ' . ($c['address2'] ?? '')) : (string)($c['billing_address'] ?? ''),
        'city' => $same ? (string)($c['city'] ?? '') : (string)($c['billing_city'] ?? ''),
        'email' => ($c['billing_email'] ?? null) ?: ($c['email'] ?? null),
        'notes' => $c['billing_notes'] ?? null,
        'siret' => $c['siret'] ?? null, 'vat' => $c['vat_number'] ?? null, 'finess' => $c['finess'] ?? null,
        'same' => $same,
    ];
}

// ---------------------------------------------------------------- Logo de l'entreprise

function brand_dir(): string
{
    $d = uploads_path('brand');
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
    if (!is_file($d . '/.htaccess') && is_file(ROOT . '/uploads/products/.htaccess')) {
        @copy(ROOT . '/uploads/products/.htaccess', $d . '/.htaccess');
    }
    return $d;
}

/** URL relative du logo (ou null s'il n'y en a pas). */
function brand_logo_url(): ?string
{
    $f = setting('brand_logo');
    return ($f && is_file(uploads_path('brand/' . basename($f)))) ? uploads_url('brand/' . rawurlencode(basename($f))) . '?v=' . substr(md5($f), 0, 6) : null;
}

/** Version JPEG du logo (fond blanc) pour les documents PDF. */
function brand_logo_pdf_path(): ?string
{
    $f = setting('brand_logo_pdf');
    $p = $f ? uploads_path('brand/' . basename($f)) : null;
    return $p && is_file($p) ? $p : null;
}

/** Enregistre un nouveau logo (PNG, JPEG, WEBP) et prépare sa version PDF. */
function brand_logo_save(string $field): void
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Envoi du logo impossible (3 Mo maximum).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
    $info = @getimagesize($f['tmp_name']);
    if (!$ext || !$info) {
        throw new RuntimeException('Format de logo non pris en charge : utilisez un PNG (idéalement à fond transparent), un JPEG ou un WEBP.');
    }
    $src = match ($ext) {
        'png' => @imagecreatefrompng($f['tmp_name']),
        'jpg' => @imagecreatefromjpeg($f['tmp_name']),
        default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
    };
    if (!$src) {
        throw new RuntimeException('Logo illisible.');
    }
    $dir = brand_dir();
    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    // Version écran : PNG transparent, 800 px de large au maximum
    [$w, $h] = [imagesx($src), imagesy($src)];
    $ratio = min(1, 800 / max($w, $h));
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));
    $screen = imagecreatetruecolor($nw, $nh);
    imagealphablending($screen, false);
    imagesavealpha($screen, true);
    imagefill($screen, 0, 0, imagecolorallocatealpha($screen, 255, 255, 255, 127));
    imagecopyresampled($screen, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagepng($screen, "$dir/logo-$id.png", 9);
    // Version PDF : JPEG sur fond blanc
    $pdf = imagecreatetruecolor($nw, $nh);
    imagefill($pdf, 0, 0, imagecolorallocate($pdf, 255, 255, 255));
    imagecopyresampled($pdf, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($pdf, false);
    imagejpeg($pdf, "$dir/logo-$id-pdf.jpg", 92);
    brand_logo_delete();
    set_setting('brand_logo', "logo-$id.png");
    set_setting('brand_logo_pdf', "logo-$id-pdf.jpg");
}

function brand_logo_delete(): void
{
    foreach (['brand_logo', 'brand_logo_pdf'] as $k) {
        if ($f = setting($k)) {
            @unlink(uploads_path('brand/' . basename($f)));
        }
        set_setting($k, null);
    }
}

// ---------------------------------------------------------------- Mode de commande fournisseur

const ORDER_METHODS = [
    'online' => 'Commande en ligne (site du fournisseur)',
    'email'  => 'Bon de commande PDF par e-mail',
    'phone'  => 'Par téléphone',
    'other'  => 'Autre (commercial, fax, EDI…)',
];

/** Mode de commande normalisé (les anciennes saisies libres « Site web », « E-mail »… sont reconnues). */
function supplier_order_method(array $s): string
{
    $m = (string)($s['order_method'] ?? '');
    if (isset(ORDER_METHODS[$m])) {
        return $m;
    }
    $n = search_normalize($m);
    return match (true) {
        $m === '' => !empty($s['email'] ?? $s['supplier_email'] ?? null) ? 'email' : 'other',
        (bool)preg_match('/site|web|ligne|internet|portail|extranet/', $n) => 'online',
        (bool)preg_match('/mail|courriel|pdf/', $n) => 'email',
        (bool)preg_match('/tel|phone/', $n) => 'phone',
        default => 'other',
    };
}

/** Page où passer la commande en ligne (adresse dédiée, sinon site web du fournisseur). */
function supplier_order_url(array $s): ?string
{
    $u = trim((string)($s['order_url'] ?? '')) ?: trim((string)($s['website'] ?? ''));
    if ($u === '') {
        return null;
    }
    return preg_match('#^https?://#i', $u) ? $u : 'https://' . $u;
}

/** Destinataire, objet et texte de l'e-mail de commande (envoi par l'application ou par la messagerie de l'ordinateur). */
function po_mail_draft(array $ids, ?string $customMessage = null): array
{
    $first = one('SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.contact_name, s.customer_number, c.name AS center_name
                  FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id WHERE po.id = ?', [$ids[0]]) ?? abort(404);
    $label = count($ids) > 1 && $first['group_ref'] ? $first['group_ref'] : $first['po_number'];
    $me = user();
    $company = setting('company_name') ?: app_name();
    $body = trim((string)$customMessage) ?: ("Bonjour" . ($first['contact_name'] ? ' ' . $first['contact_name'] : '') . ",\n\nVeuillez trouver ci-joint notre commande " . $label
        . ($first['customer_number'] ? ' (n° client ' . $first['customer_number'] . ')' : '') . ".\nMerci de nous confirmer sa bonne réception et le délai de livraison.\n\nCordialement,\n"
        . trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) . "\n" . $company);
    return [
        'to' => (string)$first['supplier_email'], 'subject' => 'Commande ' . $label . ' — ' . $company,
        'body' => $body, 'label' => $label, 'filename' => $label . '.pdf', 'first' => $first,
    ];
}

/** En-tête MIME encodé (accents). */
function mime_header(string $s): string
{
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/**
 * Brouillon d'e-mail (.eml) avec le PDF joint : ouvert par Outlook ou la messagerie Windows,
 * il apparaît comme un nouveau message prêt à envoyer (en-tête X-Unsent).
 */
function po_eml(array $ids): string
{
    $d = po_mail_draft($ids);
    $b = 'cmd-' . bin2hex(random_bytes(8));
    $me = user();
    $from = $me['email'] ? 'From: ' . mime_header(trim($me['first_name'] . ' ' . $me['last_name'])) . ' <' . $me['email'] . ">\r\n" : '';
    return "X-Unsent: 1\r\n" . $from
        . ($d['to'] ? 'To: ' . $d['to'] . "\r\n" : '')
        . 'Subject: ' . mime_header($d['subject']) . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"\r\n\r\n"
        . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($d['body'])) . "\r\n"
        . "--$b\r\nContent-Type: application/pdf; name=\"{$d['filename']}\"\r\nContent-Disposition: attachment; filename=\"{$d['filename']}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode(po_pdf($ids))) . "\r\n--$b--\r\n";
}
