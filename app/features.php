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

function price_record(int $productId, float $catalog, ?float $negotiated, string $source): void
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
        if ($old > 0 && $new > $old * 1.005) {
            $p = one('SELECT p.name, s.name AS supplier_name FROM products p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [$productId]);
            notify(admin_ids(), 'price_increase', 'Hausse de prix : ' . $p['name'],
                $p['supplier_name'] . ' : ' . money($old) . ' → ' . money($new) . ' (+' . round(($new / $old - 1) * 100, 1) . ' %).',
                url('admin/product', ['id' => $productId]));
        }
    }
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
    if (in_array($u['role'] ?? '', ['manager', 'admin'], true)) {
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
    $d = ROOT . '/storage/invoices';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** Enregistre un justificatif de facture (PDF ou image) hors de la zone web. */
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
