<?php
declare(strict_types=1);

/**
 * Règles métier : catalogue par centre, panier, demandes, bons de commande, réceptions.
 */

const PO_STATUSES = [
    'a_commander' => ['label' => 'À commander',        'color' => 'amber'],
    'commande'    => ['label' => 'Commandé',           'color' => 'blue'],
    'partiel'     => ['label' => 'Reçu partiellement', 'color' => 'violet'],
    'recu'        => ['label' => 'Reçu',               'color' => 'green'],
    'annule'      => ['label' => 'Annulé',             'color' => 'gray'],
];

const LINE_STATUSES = [
    'awaiting'  => ['label' => 'À valider (responsable)', 'color' => 'pink'],
    'pending'   => ['label' => 'En attente',     'color' => 'amber'],
    'in_po'     => ['label' => 'Bon de commande', 'color' => 'blue'],
    'cancelled' => ['label' => 'Refusée',        'color' => 'gray'],
    'transferred' => ['label' => 'Transférée d\'un autre centre', 'color' => 'green'],
];

function po_status_badge(string $s): string
{
    $d = PO_STATUSES[$s] ?? ['label' => $s, 'color' => 'gray'];
    return '<span class="badge badge-' . $d['color'] . '">' . e($d['label']) . '</span>';
}

/** Statut lisible d'une ligne de demande, selon le bon de commande associé. */
function request_line_status(array $l): array
{
    if ($l['status'] === 'cancelled') {
        return ['label' => 'Refusée', 'color' => 'gray'];
    }
    if ($l['status'] === 'awaiting') {
        return ['label' => 'À valider par le responsable', 'color' => 'pink'];
    }
    if ($l['status'] === 'transferred') {
        return ['label' => $l['cancel_reason'] ? 'Transférée · ' . mb_strtolower(mb_substr($l['cancel_reason'], 0, 1)) . mb_substr($l['cancel_reason'], 1) : 'Transférée d\'un autre centre', 'color' => 'green'];
    }
    if ($l['status'] === 'pending' || empty($l['po_status'])) {
        return ['label' => 'En attente', 'color' => 'amber'];
    }
    return match ($l['po_status']) {
        'a_commander' => ['label' => 'Validée — à commander', 'color' => 'amber'],
        'commande'    => ['label' => 'Commandée', 'color' => 'blue'],
        'partiel'     => ['label' => 'Livraison partielle', 'color' => 'violet'],
        'recu'        => ['label' => 'Reçue', 'color' => 'green'],
        'annule'      => ['label' => 'Annulée', 'color' => 'gray'],
        default       => ['label' => $l['po_status'], 'color' => 'gray'],
    };
}

function badge(array $s): string
{
    return '<span class="badge badge-' . e($s['color']) . '">' . e($s['label']) . '</span>';
}

/** Prix appliqué : le tarif négocié s'il existe, sinon le tarif catalogue. */
function effective_price(array $p): float
{
    return ($p['negotiated_price'] !== null && $p['negotiated_price'] !== '' && (float)$p['negotiated_price'] > 0)
        ? (float)$p['negotiated_price']
        : (float)$p['catalog_price'];
}

function show_prices(): bool
{
    return is_admin() || setting('show_prices', '1') === '1';
}

// ---------------------------------------------------------------- Catalogue

/** Condition SQL limitant les fournisseurs à ceux autorisés pour un centre. */
function supplier_visible_sql(string $alias = 's'): string
{
    return "($alias.active = 1 AND ($alias.all_centers = 1 OR EXISTS (
        SELECT 1 FROM supplier_centers sc WHERE sc.supplier_id = $alias.id AND sc.center_id = ?)))";
}

function catalog_products(int $centerId, array $filters = []): array
{
    $sql = 'SELECT p.*, s.name AS supplier_name, s.color AS supplier_color,
                   c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM products p
            JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.active = 1 AND ' . supplier_visible_sql('s');
    $params = [$centerId];
    if (!empty($filters['category'])) {
        $sql .= ' AND p.category_id = ?';
        $params[] = (int)$filters['category'];
    }
    if (!empty($filters['supplier'])) {
        $sql .= ' AND p.supplier_id = ?';
        $params[] = (int)$filters['supplier'];
    }
    if (!empty($filters['ids'])) {
        $sql .= ' AND p.id IN ' . in_list($filters['ids']);
        $params = array_merge($params, array_map('intval', $filters['ids']));
    }
    $sql .= ' ORDER BY p.name';
    return all($sql, $params);
}

function product_visible_for_center(int $productId, int $centerId): ?array
{
    return one('SELECT p.* FROM products p JOIN suppliers s ON s.id = p.supplier_id
                WHERE p.id = ? AND p.active = 1 AND ' . supplier_visible_sql('s'), [$productId, $centerId]);
}

/** Popularité : quantités demandées sur 12 mois (sert au tri et aux suggestions). */
function product_popularity(int $centerId): array
{
    $since = date('Y-m-d H:i:s', strtotime('-12 months'));
    $rows = all('SELECT product_id, COUNT(*) n FROM request_lines WHERE center_id = ? AND created_at >= ? GROUP BY product_id', [$centerId, $since]);
    return array_column($rows, 'n', 'product_id');
}

function user_favorites(int $userId): array
{
    return array_map('intval', array_column(all('SELECT product_id FROM favorites WHERE user_id = ?', [$userId]), 'product_id'));
}

// ---------------------------------------------------------------- Panier

function cart_items(int $userId, int $centerId): array
{
    return all('SELECT ci.*, p.name, p.reference, p.unit, p.image, p.catalog_price, p.negotiated_price, p.supplier_id,
                       s.name AS supplier_name, s.color AS supplier_color
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                JOIN suppliers s ON s.id = p.supplier_id
                WHERE ci.user_id = ? AND ci.center_id = ?
                ORDER BY s.name, p.name', [$userId, $centerId]);
}

function cart_count(int $userId, int $centerId): int
{
    return (int)val('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE user_id = ? AND center_id = ?', [$userId, $centerId]);
}

function cart_add(int $userId, int $centerId, int $productId, int $qty): void
{
    $qty = max(1, min(9999, $qty));
    $existing = one('SELECT id, qty FROM cart_items WHERE user_id = ? AND center_id = ? AND product_id = ?', [$userId, $centerId, $productId]);
    if ($existing) {
        update('cart_items', ['qty' => min(9999, (int)$existing['qty'] + $qty)], 'id = ?', [$existing['id']]);
    } else {
        insert('cart_items', ['user_id' => $userId, 'center_id' => $centerId, 'product_id' => $productId, 'qty' => $qty, 'created_at' => now()]);
    }
}

/** Transforme le panier en demande ; les lignes sont classées par fournisseur. */
function cart_submit(int $userId, int $centerId, string $comment, bool $urgent): int
{
    return tx(function () use ($userId, $centerId, $comment, $urgent) {
        $items = cart_items($userId, $centerId);
        $offCatalog = cart_suggestions($userId, $centerId);
        if (!$items && !$offCatalog) {
            throw new RuntimeException('Le panier est vide.');
        }
        // Au-delà du seuil paramétré, la demande passe d'abord par le responsable du centre
        $total = array_sum(array_map(fn($it) => effective_price($it) * (int)$it['qty'], $items));
        $approval = $items && request_needs_approval($centerId, $userId, $total);
        $requestId = insert('requests', [
            'center_id' => $centerId, 'user_id' => $userId,
            'comment' => $comment ?: null, 'urgent' => $urgent ? 1 : 0, 'created_at' => now(),
            'approval_status' => $approval ? 'pending' : null,
        ]);
        foreach ($items as $it) {
            insert('request_lines', [
                'request_id' => $requestId, 'center_id' => $centerId,
                'product_id' => $it['product_id'], 'supplier_id' => $it['supplier_id'],
                'qty' => $it['qty'], 'unit_price' => effective_price($it),
                'comment' => $it['comment'], 'status' => $approval ? 'awaiting' : 'pending', 'created_at' => now(),
            ]);
        }
        q('DELETE FROM cart_items WHERE user_id = ? AND center_id = ?', [$userId, $centerId]);
        // Les articles hors catalogue partent avec la demande, en attente d'examen par le service achats
        q('UPDATE product_suggestions SET in_cart = 0, request_id = ? WHERE user_id = ? AND center_id = ? AND in_cart = 1',
            [$requestId, $userId, $centerId]);
        return $requestId;
    });
}

// ---------------------------------------------------------------- Bons de commande

function next_po_number(): string
{
    $prefix = 'BC-' . date('Y') . '-';
    $last = val('SELECT po_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $n = $last ? (int)substr((string)$last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function po_log(int $poId, string $action, ?string $details = null): void
{
    insert('po_history', [
        'purchase_order_id' => $poId, 'user_id' => user()['id'] ?? null,
        'action' => $action, 'details' => $details, 'created_at' => now(),
    ]);
}

/**
 * Crée un bon de commande pour un centre et un fournisseur à partir de lignes de demande.
 * Les quantités d'un même article sont regroupées sur une seule ligne.
 */
function po_create(int $centerId, int $supplierId, array $requestLineIds, string $notes = ''): int
{
    return tx(function () use ($centerId, $supplierId, $requestLineIds, $notes) {
        $lines = $requestLineIds ? all('SELECT rl.*, p.name, p.reference, p.unit, p.catalog_price, p.negotiated_price
            FROM request_lines rl JOIN products p ON p.id = rl.product_id
            WHERE rl.id IN ' . in_list($requestLineIds) . " AND rl.status = 'pending' AND rl.center_id = ? AND rl.supplier_id = ?",
            array_merge(array_map('intval', $requestLineIds), [$centerId, $supplierId])) : [];
        if (!$lines) {
            throw new RuntimeException('Aucune ligne de demande valide sélectionnée.');
        }
        $supplier = one('SELECT * FROM suppliers WHERE id = ?', [$supplierId]);
        $poId = insert('purchase_orders', [
            'po_number' => next_po_number(), 'center_id' => $centerId, 'supplier_id' => $supplierId,
            'status' => 'a_commander', 'shipping_fee' => 0, 'notes' => $notes ?: null,
            'created_by' => user()['id'] ?? null, 'created_at' => now(),
        ]);
        $grouped = [];
        foreach ($lines as $l) {
            $pid = (int)$l['product_id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = $l + ['total_qty' => 0];
            }
            $grouped[$pid]['total_qty'] += (int)$l['qty'];
        }
        foreach ($grouped as $pid => $g) {
            insert('purchase_order_lines', [
                'purchase_order_id' => $poId, 'product_id' => $pid,
                'reference' => $g['reference'], 'label' => $g['name'], 'unit' => $g['unit'],
                'qty' => $g['total_qty'], 'unit_price' => effective_price($g),
                'catalog_price' => (float)$g['catalog_price'],
            ]);
        }
        q("UPDATE request_lines SET status = 'in_po', purchase_order_id = ? WHERE id IN " . in_list(array_column($lines, 'id')),
            array_merge([$poId], array_column($lines, 'id')));
        po_recompute_shipping($poId, $supplier);
        po_log($poId, 'Création', count($lines) . ' ligne(s) de demande regroupée(s)');
        return $poId;
    });
}

function po_totals(int $poId): array
{
    $r = one('SELECT COALESCE(SUM(qty * unit_price),0) total, COALESCE(SUM(qty * catalog_price),0) catalog,
                     COALESCE(SUM(qty),0) qty, COALESCE(SUM(qty_received),0) received, COUNT(*) nb_lines
              FROM purchase_order_lines WHERE purchase_order_id = ?', [$poId]);
    return [
        'total' => (float)$r['total'], 'catalog' => (float)$r['catalog'],
        'savings' => max(0, (float)$r['catalog'] - (float)$r['total']),
        'qty' => (int)$r['qty'], 'received' => (int)$r['received'], 'lines' => (int)$r['nb_lines'],
    ];
}

/** Frais de port appliqués automatiquement selon le franco du fournisseur. */
function po_recompute_shipping(int $poId, ?array $supplier = null): void
{
    $po = one('SELECT * FROM purchase_orders WHERE id = ?', [$poId]);
    $supplier ??= one('SELECT * FROM suppliers WHERE id = ?', [$po['supplier_id']]);
    $total = po_totals($poId)['total'];
    $free = (float)$supplier['free_shipping_from'];
    $fee = ($free > 0 && $total >= $free) ? 0.0 : (float)$supplier['shipping_fee'];
    update('purchase_orders', ['shipping_fee' => $fee], 'id = ?', [$poId]);
}

/** Met à jour le statut d'un bon après une réception. */
function po_refresh_reception_status(int $poId): string
{
    $t = po_totals($poId);
    $po = one('SELECT status FROM purchase_orders WHERE id = ?', [$poId]);
    if (!in_array($po['status'], ['commande', 'partiel', 'recu'], true)) {
        return $po['status'];
    }
    $status = $t['received'] <= 0 ? 'commande' : ($t['received'] >= $t['qty'] ? 'recu' : 'partiel');
    update('purchase_orders', [
        'status' => $status,
        'received_at' => $status === 'recu' ? now() : null,
    ], 'id = ?', [$poId]);
    return $status;
}

// ---------------------------------------------------------------- Dates limites

function upcoming_deadlines(?int $centerId, int $limit = 6, bool $includePastDay = true): array
{
    $from = $includePastDay ? date('Y-m-d H:i:s', strtotime('-1 day')) : now();
    $sql = 'SELECT d.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS center_name
            FROM deadlines d
            LEFT JOIN suppliers s ON s.id = d.supplier_id
            LEFT JOIN centers c ON c.id = d.center_id
            WHERE d.deadline_at >= ?';
    $params = [$from];
    if ($centerId !== null) {
        $sql .= ' AND (d.center_id IS NULL OR d.center_id = ?)';
        $params[] = $centerId;
    }
    $sql .= ' ORDER BY d.deadline_at ASC LIMIT ' . (int)$limit;
    return all($sql, $params);
}

/** Prochaine date limite pour un fournisseur dans un centre (affichée dans le catalogue). */
function supplier_deadlines(int $centerId): array
{
    $rows = all('SELECT supplier_id, MIN(deadline_at) d FROM deadlines
                 WHERE supplier_id IS NOT NULL AND deadline_at >= ? AND (center_id IS NULL OR center_id = ?)
                 GROUP BY supplier_id', [now(), $centerId]);
    return array_column($rows, 'd', 'supplier_id');
}

// ---------------------------------------------------------------- Statistiques

function monthly_spend(?int $centerId = null, int $months = 12): array
{
    $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
    $sql = "SELECT SUBSTR(po.ordered_at, 1, 7) m, SUM(l.qty * l.unit_price) total
            FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id
            WHERE po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?";
    $params = [$start];
    if ($centerId) {
        $sql .= ' AND po.center_id = ?';
        $params[] = $centerId;
    }
    $sql .= ' GROUP BY SUBSTR(po.ordered_at, 1, 7)';
    $data = array_column(all($sql, $params), 'total', 'm');
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $k = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
        $out[] = ['month' => $k, 'label' => month_short_fr((int)substr($k, 5, 2)), 'total' => (float)($data[$k] ?? 0)];
    }
    return $out;
}

/** Lignes en attente regroupées par centre puis fournisseur (vue « demandes à traiter »). */
function pending_groups(?int $centerFilter = null): array
{
    $sql = "SELECT rl.*, p.name AS product_name, p.reference, p.unit, p.image,
                   s.name AS supplier_name, s.color AS supplier_color, s.min_order_amount, s.free_shipping_from, s.shipping_fee,
                   c.name AS center_name, c.color AS center_color,
                   u.first_name, u.last_name, u.job, r.urgent, r.comment AS request_comment,
                   st.qty AS stock_qty, st.alert_qty AS stock_alert, st.location AS stock_location,
                   (SELECT COALESCE(SUM(pl.qty - pl.qty_received), 0) FROM purchase_order_lines pl JOIN purchase_orders po ON po.id = pl.purchase_order_id
                     WHERE po.center_id = rl.center_id AND pl.product_id = rl.product_id AND po.status IN ('a_commander','commande','partiel')) AS stock_on_order
            FROM request_lines rl
            JOIN products p ON p.id = rl.product_id
            JOIN suppliers s ON s.id = rl.supplier_id
            JOIN centers c ON c.id = rl.center_id
            JOIN requests r ON r.id = rl.request_id
            JOIN users u ON u.id = r.user_id
            LEFT JOIN stock st ON st.center_id = rl.center_id AND st.product_id = rl.product_id
            WHERE rl.status = 'pending'";
    $params = [];
    if ($centerFilter) {
        $sql .= ' AND rl.center_id = ?';
        $params[] = $centerFilter;
    }
    $sql .= ' ORDER BY s.name, c.name, rl.created_at';
    $groups = [];
    foreach (all($sql, $params) as $l) {
        $k = $l['supplier_id'] . '-' . $l['center_id'];
        if (!isset($groups[$k])) {
            $groups[$k] = [
                'supplier_id' => (int)$l['supplier_id'], 'supplier_name' => $l['supplier_name'], 'supplier_color' => $l['supplier_color'],
                'center_id' => (int)$l['center_id'], 'center_name' => $l['center_name'], 'center_color' => $l['center_color'],
                'min_order_amount' => (float)$l['min_order_amount'], 'free_shipping_from' => (float)$l['free_shipping_from'],
                'shipping_fee' => (float)$l['shipping_fee'],
                'lines' => [], 'total' => 0.0, 'urgent' => false, 'oldest' => $l['created_at'],
            ];
        }
        $groups[$k]['lines'][] = $l;
        $groups[$k]['total'] += (int)$l['qty'] * (float)$l['unit_price'];
        $groups[$k]['urgent'] = $groups[$k]['urgent'] || (bool)$l['urgent'];
        $groups[$k]['oldest'] = min($groups[$k]['oldest'], $l['created_at']);
    }
    return array_values($groups);
}

// ---------------------------------------------------------------- Articles hors catalogue

const SUGGESTION_STATUSES = [
    'pending'  => ['label' => 'En examen',            'color' => 'amber'],
    'added'    => ['label' => 'Ajouté au catalogue',  'color' => 'green'],
    'linked'   => ['label' => 'Article existant',     'color' => 'blue'],
    'rejected' => ['label' => 'Refusé',               'color' => 'gray'],
];

function cart_suggestions(int $userId, int $centerId): array
{
    return all('SELECT * FROM product_suggestions WHERE user_id = ? AND center_id = ? AND in_cart = 1 ORDER BY created_at', [$userId, $centerId]);
}

/** Propositions transmises au service achats (hors paniers non envoyés). */
function pending_suggestions_count(): int
{
    try {
        return (int)val("SELECT COUNT(*) FROM product_suggestions WHERE status = 'pending' AND in_cart = 0");
    } catch (Throwable) {
        return 0;
    }
}
