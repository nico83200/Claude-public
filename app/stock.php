<?php
declare(strict_types=1);

/**
 * Inventaire par centre : le stock augmente avec les réceptions et se corrige
 * par inventaire (stock compté) ou par sortie déclarée.
 */

const STOCK_MOVE_TYPES = [
    'reception'  => ['label' => 'Réception',  'color' => 'green'],
    'inventaire' => ['label' => 'Inventaire', 'color' => 'blue'],
    'sortie'     => ['label' => 'Sortie',     'color' => 'amber'],
    'ajout'      => ['label' => 'Entrée manuelle', 'color' => 'violet'],
    'correction' => ['label' => 'Correction réception', 'color' => 'gray'],
    'transfert'  => ['label' => 'Transfert', 'color' => 'pink'],
];

function stock_row(int $centerId, int $productId): ?array
{
    return one('SELECT * FROM stock WHERE center_id = ? AND product_id = ?', [$centerId, $productId]);
}

/** Enregistre un mouvement et met à jour la quantité en stock (jamais négative). */
function stock_move(int $centerId, int $productId, int $delta, string $type, ?string $note = null, ?int $poId = null): int
{
    return tx(function () use ($centerId, $productId, $delta, $type, $note, $poId) {
        $row = stock_row($centerId, $productId);
        $before = (int)($row['qty'] ?? 0);
        $after = max(0, $before + $delta);
        $data = ['qty' => $after, 'updated_at' => now()];
        if ($type === 'inventaire') {
            $data['counted_at'] = now();
        }
        if ($row) {
            update('stock', $data, 'center_id = ? AND product_id = ?', [$centerId, $productId]);
        } else {
            insert('stock', $data + ['center_id' => $centerId, 'product_id' => $productId, 'alert_qty' => 0]);
        }
        if ($after !== $before || $type === 'inventaire') {
            insert('stock_movements', [
                'center_id' => $centerId, 'product_id' => $productId, 'type' => $type,
                'delta' => $after - $before, 'qty_after' => $after, 'purchase_order_id' => $poId,
                'user_id' => user()['id'] ?? null, 'note' => $note ? mb_substr($note, 0, 255) : null, 'created_at' => now(),
            ]);
        }
        $alert = (int)($row['alert_qty'] ?? 0);
        if ($alert > 0 && $before > $alert && $after <= $alert) {
            notify_stock_low($centerId, $productId, $after, $alert);
            stock_auto_reorder($centerId, $productId);
        }
        return $after;
    });
}

/** Inventaire : fixe la quantité réellement comptée. */
function stock_count(int $centerId, int $productId, int $counted, ?string $note = null): int
{
    $before = (int)(stock_row($centerId, $productId)['qty'] ?? 0);
    return stock_move($centerId, $productId, max(0, $counted) - $before, 'inventaire', $note);
}

function stock_set_alert(int $centerId, int $productId, int $alert): void
{
    if (stock_row($centerId, $productId)) {
        update('stock', ['alert_qty' => max(0, $alert)], 'center_id = ? AND product_id = ?', [$centerId, $productId]);
    } else {
        insert('stock', ['center_id' => $centerId, 'product_id' => $productId, 'qty' => 0, 'alert_qty' => max(0, $alert), 'updated_at' => now()]);
    }
}

/** Emplacement de rangement d'un article dans un centre (lieu, étagère…). */
function stock_set_location(int $centerId, int $productId, ?string $location): void
{
    $location = trim(preg_replace('/\s+/', ' ', (string)$location));
    $location = $location === '' ? null : mb_substr($location, 0, 80);
    if (stock_row($centerId, $productId)) {
        update('stock', ['location' => $location], 'center_id = ? AND product_id = ?', [$centerId, $productId]);
    } else {
        insert('stock', ['center_id' => $centerId, 'product_id' => $productId, 'qty' => 0, 'alert_qty' => 0, 'location' => $location, 'updated_at' => now()]);
    }
}

/** Articles suivis en stock dans un centre. */
function stock_list(int $centerId, string $filter = ''): array
{
    $sql = 'SELECT st.*, p.name, p.reference, p.barcode, p.unit, p.image, p.catalog_price, p.negotiated_price, p.supplier_id, p.active,
                   s.name AS supplier_name, s.color AS supplier_color,
                   c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
                   (SELECT COALESCE(SUM(l.qty - l.qty_received),0) FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id
                     WHERE po.center_id = st.center_id AND l.product_id = st.product_id AND po.status IN (\'a_commander\',\'commande\',\'partiel\')) AS on_order
            FROM stock st
            JOIN products p ON p.id = st.product_id
            JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE st.center_id = ?';
    if ($filter === 'low') {
        $sql .= ' AND st.alert_qty > 0 AND st.qty <= st.alert_qty';
    } elseif ($filter === 'empty') {
        $sql .= ' AND st.qty = 0';
    }
    $sql .= ' ORDER BY c.position, c.name, p.name';
    return all($sql, [$centerId]);
}

function stock_low_count(int $centerId): int
{
    return (int)val('SELECT COUNT(*) FROM stock WHERE center_id = ? AND alert_qty > 0 AND qty <= alert_qty', [$centerId]);
}

/** Répercute une réception (variation de quantité reçue) sur le stock du centre. */
function stock_from_reception(array $po, array $line, int $oldReceived, int $newReceived): void
{
    if (empty($line['product_id']) || $oldReceived === $newReceived) {
        return;
    }
    $delta = $newReceived - $oldReceived;
    stock_move((int)$po['center_id'], (int)$line['product_id'], $delta, $delta > 0 ? 'reception' : 'correction',
        $po['po_number'] . ' — ' . $po['supplier_name'], (int)$po['id']);
}

// ---------------------------------------------------------------- Pilotage : consommation, seuils conseillés, réapprovisionnement

const STOCK_USAGE_DAYS = 90;
const STOCK_SAFETY_DAYS = 7;

/** Quantités sorties par article sur 90 jours dans un centre (sorties déclarées et écarts négatifs d'inventaire). */
function stock_usage_map(int $centerId): array
{
    $rows = all("SELECT product_id, SUM(-delta) AS qty FROM stock_movements
                 WHERE center_id = ? AND delta < 0 AND type IN ('sortie', 'inventaire') AND created_at >= ? GROUP BY product_id",
        [$centerId, date('Y-m-d H:i:s', strtotime('-' . STOCK_USAGE_DAYS . ' days'))]);
    return array_map('intval', array_column($rows, 'qty', 'product_id'));
}

/** Délai fournisseur en jours, lu dans le texte saisi (« 48 h », « 3 jours », « 1 semaine », « J+2 »…). 3 jours par défaut. */
function supplier_lead_days(?string $delay): int
{
    $t = mb_strtolower(trim((string)$delay));
    if ($t === '' || !preg_match('/(\d+)(?:\s*(?:à|-)\s*(\d+))?\s*(h|heure|j|jour|sem|semaine|mois)?/u', $t, $m)) {
        return 3;
    }
    $n = (int)(!empty($m[2]) ? $m[2] : $m[1]);
    return max(1, match (true) {
        str_starts_with($m[3] ?? '', 'h') => (int)ceil($n / 24),
        str_starts_with($m[3] ?? '', 'sem') => $n * 7,
        str_starts_with($m[3] ?? '', 'mois') => $n * 30,
        default => $n,
    });
}

/**
 * Seuil d'alerte conseillé : consommation moyenne journalière × (délai fournisseur + 7 jours de sécurité).
 * Null si l'article n'a pas été consommé sur la période.
 */
function stock_advised_threshold(int $out90, int $leadDays): ?int
{
    if ($out90 <= 0) {
        return null;
    }
    return max(1, (int)ceil($out90 / STOCK_USAGE_DAYS * ($leadDays + STOCK_SAFETY_DAYS)));
}

/** Seuils conseillés des articles suivis d'un centre : [product_id => ['advised' => n, 'out90' => n, 'lead' => j]]. */
function stock_advice(int $centerId): array
{
    $usage = stock_usage_map($centerId);
    $out = [];
    foreach (all('SELECT st.product_id, s.delivery_delay FROM stock st JOIN products p ON p.id = st.product_id JOIN suppliers s ON s.id = p.supplier_id WHERE st.center_id = ?', [$centerId]) as $r) {
        $lead = supplier_lead_days($r['delivery_delay']);
        $o = $usage[(int)$r['product_id']] ?? 0;
        $out[(int)$r['product_id']] = ['advised' => stock_advised_threshold($o, $lead), 'out90' => $o, 'lead' => $lead];
    }
    return $out;
}

/** Quantité à commander pour remonter le stock : couvrir le double du seuil, déduction faite de ce qui est déjà en commande. */
function stock_reorder_qty(int $qty, int $alert, int $onOrder, int $minQty = 1): int
{
    $need = max($alert * 2, $alert + 1) - $qty - $onOrder;
    return $need > 0 ? max($need, $minQty) : 0;
}

/**
 * Réapprovisionnement automatique : quand un article passe sous son seuil, une demande est créée
 * dans « Demandes à traiter » (sauf si une demande est déjà en cours ou la quantité en commande suffit).
 * Renvoie l'identifiant de la demande créée, ou null.
 */
function stock_auto_reorder(int $centerId, int $productId): ?int
{
    if (setting('auto_reorder', '1') !== '1') {
        return null;
    }
    $p = one('SELECT p.*, st.qty, st.alert_qty FROM products p JOIN stock st ON st.product_id = p.id AND st.center_id = ? WHERE p.id = ? AND p.active = 1', [$centerId, $productId]);
    if (!$p || (int)$p['alert_qty'] <= 0 || (int)$p['qty'] > (int)$p['alert_qty']) {
        return null;
    }
    if (val("SELECT COUNT(*) FROM request_lines WHERE center_id = ? AND product_id = ? AND status IN ('pending', 'awaiting')", [$centerId, $productId])) {
        return null;
    }
    $onOrder = (int)val("SELECT COALESCE(SUM(l.qty - l.qty_received), 0) FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id
                         WHERE po.center_id = ? AND l.product_id = ? AND po.status IN ('a_commander', 'commande', 'partiel')", [$centerId, $productId]);
    $qty = stock_reorder_qty((int)$p['qty'], (int)$p['alert_qty'], $onOrder, max(1, (int)($p['min_qty'] ?? 1)));
    if ($qty <= 0) {
        return null;
    }
    $userId = (int)(user()['id'] ?? 0) ?: (int)val("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
    if (!$userId) {
        return null;
    }
    $requestId = insert('requests', [
        'center_id' => $centerId, 'user_id' => $userId, 'urgent' => (int)$p['qty'] === 0 ? 1 : 0, 'created_at' => now(),
        'comment' => 'Réapprovisionnement automatique : stock ' . (int)$p['qty'] . ' pour un seuil de ' . (int)$p['alert_qty'] . '.',
    ]);
    insert('request_lines', [
        'request_id' => $requestId, 'center_id' => $centerId, 'product_id' => $productId, 'supplier_id' => $p['supplier_id'],
        'qty' => $qty, 'unit_price' => effective_price($p), 'comment' => 'Automatique (stock bas)', 'status' => 'pending', 'created_at' => now(),
    ]);
    return $requestId;
}

/**
 * Centres disposant d'un excédent d'un article : stock au-delà du seuil d'alerte suffisant pour la quantité demandée.
 * Renvoie [product_id => [[center_id, center_name, qty, spare], …]] pour les articles demandés.
 */
function stock_transfer_offers(array $productIds): array
{
    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if (!$productIds) {
        return [];
    }
    $out = [];
    foreach (all('SELECT st.product_id, st.center_id, st.qty, st.alert_qty, c.name AS center_name FROM stock st JOIN centers c ON c.id = st.center_id
                  WHERE c.active = 1 AND st.qty > st.alert_qty AND st.product_id IN ' . in_list($productIds) . ' ORDER BY st.qty DESC', $productIds) as $r) {
        $out[(int)$r['product_id']][] = ['center_id' => (int)$r['center_id'], 'center_name' => $r['center_name'], 'qty' => (int)$r['qty'], 'spare' => (int)$r['qty'] - (int)$r['alert_qty']];
    }
    return $out;
}

/** Sert une ligne de demande par un transfert depuis un autre centre plutôt que par une commande. */
function request_line_transfer(int $lineId, int $fromCenter): array
{
    return tx(function () use ($lineId, $fromCenter) {
        $l = one("SELECT rl.*, r.user_id, p.name, c.name AS center_name FROM request_lines rl JOIN requests r ON r.id = rl.request_id
                  JOIN products p ON p.id = rl.product_id JOIN centers c ON c.id = rl.center_id WHERE rl.id = ? AND rl.status = 'pending'", [$lineId]);
        if (!$l) {
            throw new RuntimeException('Ligne de demande introuvable ou déjà traitée.');
        }
        if ($fromCenter === (int)$l['center_id']) {
            throw new RuntimeException('Choisissez un autre centre que le demandeur.');
        }
        $src = stock_row($fromCenter, (int)$l['product_id']);
        $from = (string)val('SELECT name FROM centers WHERE id = ?', [$fromCenter]);
        if (!$src || (int)$src['qty'] - (int)$src['alert_qty'] < (int)$l['qty']) {
            throw new RuntimeException('Le stock de ' . $from . ' ne permet plus ce transfert sans passer sous son seuil.');
        }
        stock_move($fromCenter, (int)$l['product_id'], -(int)$l['qty'], 'transfert', 'Transfert vers ' . $l['center_name'] . ' (demande n°' . $l['request_id'] . ')');
        stock_move((int)$l['center_id'], (int)$l['product_id'], (int)$l['qty'], 'transfert', 'Transfert depuis ' . $from . ' (demande n°' . $l['request_id'] . ')');
        update('request_lines', ['status' => 'transferred', 'cancel_reason' => 'Fourni par ' . $from], 'id = ?', [$lineId]);
        notify([(int)$l['user_id']], 'po_received', 'Demande fournie par ' . $from . ' : ' . $l['name'],
            $l['qty'] . ' × ' . $l['name'] . ' vous sont transférés depuis ' . $from . ' plutôt que commandés.', url('requests'));
        return $l + ['from_name' => $from];
    });
}

// ---------------------------------------------------------------- Inventaire tournant

const CYCLE_COUNT_SIZE = 10;

/** Semaine de l'inventaire tournant (ex : 2026-W41). */
function cycle_week(?int $ts = null): string
{
    return date('o-\WW', $ts ?? time());
}

/**
 * Articles à compter cette semaine dans un centre : ceux qui n'ont pas été comptés depuis le plus longtemps,
 * en privilégiant les plus coûteux et les plus consommés. La liste reste la même toute la semaine.
 */
function cycle_count_list(int $centerId, ?string $week = null): array
{
    $week ??= cycle_week();
    $key = 'cycle_' . $centerId . '_' . $week;
    $ids = json_decode((string)setting($key, ''), true);
    if (!is_array($ids)) {
        $usage = stock_usage_map($centerId);
        $scored = [];
        foreach (all('SELECT st.product_id, st.qty, st.counted_at, p.catalog_price, p.negotiated_price FROM stock st JOIN products p ON p.id = st.product_id
                      WHERE st.center_id = ? AND p.active = 1', [$centerId]) as $r) {
            $days = $r['counted_at'] ? (time() - strtotime($r['counted_at'])) / 86400 : 365;
            $price = effective_price($r);
            $value = ((int)$r['qty'] + ($usage[(int)$r['product_id']] ?? 0)) * $price;
            $scored[(int)$r['product_id']] = min($days, 365) * (1 + log(1 + $value));
        }
        arsort($scored);
        $ids = array_slice(array_keys($scored), 0, CYCLE_COUNT_SIZE);
        set_setting($key, json_encode($ids));
    }
    if (!$ids) {
        return [];
    }
    $rows = all('SELECT st.*, p.name, p.unit, p.image, p.barcode, p.reference, p.catalog_price, p.negotiated_price, p.supplier_id, s.color AS supplier_color
                 FROM stock st JOIN products p ON p.id = st.product_id JOIN suppliers s ON s.id = p.supplier_id
                 WHERE st.center_id = ? AND st.product_id IN ' . in_list($ids), array_merge([$centerId], $ids));
    $byId = array_column($rows, null, 'product_id');
    return array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, $ids)));
}

/** Enregistre les comptages de l'inventaire tournant. Renvoie le nombre d'articles et l'écart valorisé (€). */
function cycle_count_save(int $centerId, array $counted, string $week): array
{
    $n = 0;
    $gap = 0.0;
    $lines = 0;
    tx(function () use ($centerId, $counted, $week, &$n, &$gap, &$lines) {
        foreach ($counted as $pid => $qty) {
            if ($qty === '' || !is_numeric($qty) || !($row = stock_row($centerId, (int)$pid))) {
                continue;
            }
            $before = (int)$row['qty'];
            stock_count($centerId, (int)$pid, (int)$qty, 'Inventaire tournant ' . $week);
            $diff = max(0, (int)$qty) - $before;
            if ($diff !== 0) {
                $lines++;
                $gap += $diff * effective_price(one('SELECT * FROM products WHERE id = ?', [(int)$pid]));
            }
            $n++;
        }
    });
    return ['counted' => $n, 'gaps' => $lines, 'value' => round($gap, 2)];
}

/** Historique des inventaires tournants : écart valorisé par semaine. */
function cycle_count_history(int $centerId, int $weeks = 12): array
{
    $rows = all("SELECT m.note, m.delta, p.catalog_price, p.negotiated_price FROM stock_movements m JOIN products p ON p.id = m.product_id
                 WHERE m.center_id = ? AND m.type = 'inventaire' AND m.note LIKE 'Inventaire tournant %' AND m.created_at >= ?",
        [$centerId, date('Y-m-d H:i:s', strtotime('-' . $weeks . ' weeks'))]);
    $out = [];
    foreach ($rows as $r) {
        $w = substr((string)$r['note'], strlen('Inventaire tournant '));
        $out[$w] ??= ['week' => $w, 'counted' => 0, 'gaps' => 0, 'value' => 0.0];
        $out[$w]['counted']++;
        if ((int)$r['delta'] !== 0) {
            $out[$w]['gaps']++;
            $out[$w]['value'] += (int)$r['delta'] * effective_price($r);
        }
    }
    krsort($out);
    return array_values($out);
}

// ---------------------------------------------------------------- Corrections de l'historique (administrateur)

/** Mouvements d'un article dans un centre situés après une position (date, id) de l'historique, du plus ancien au plus récent. */
function stock_moves_after(int $centerId, int $productId, string $at, int $id): array
{
    return all('SELECT * FROM stock_movements WHERE center_id = ? AND product_id = ? AND (created_at > ? OR (created_at = ? AND id > ?))
                ORDER BY created_at, id', [$centerId, $productId, $at, $at, $id]);
}

/**
 * Répercute une variation $d apparue à une position de l'historique : les « stock après » suivants sont décalés
 * jusqu'au prochain inventaire (la quantité comptée reste la référence, seul son écart change) ;
 * sans inventaire ultérieur, c'est le stock actuel qui est corrigé.
 */
function stock_ripple(int $centerId, int $productId, string $at, int $id, int $d): void
{
    if ($d === 0) {
        return;
    }
    foreach (stock_moves_after($centerId, $productId, $at, $id) as $m) {
        if ($m['type'] === 'inventaire') {
            update('stock_movements', ['delta' => (int)$m['delta'] - $d], 'id = ?', [$m['id']]);
            return;
        }
        update('stock_movements', ['qty_after' => max(0, (int)$m['qty_after'] + $d)], 'id = ?', [$m['id']]);
    }
    $row = stock_row($centerId, $productId);
    if ($row) {
        update('stock', ['qty' => max(0, (int)$row['qty'] + $d), 'updated_at' => now()], 'center_id = ? AND product_id = ?', [$centerId, $productId]);
    } elseif ($d > 0) {
        insert('stock', ['center_id' => $centerId, 'product_id' => $productId, 'qty' => $d, 'alert_qty' => 0, 'updated_at' => now()]);
    }
}

/** Supprime un mouvement et annule son effet sur le stock. Renvoie le mouvement supprimé. */
function stock_move_delete(int $id): ?array
{
    return tx(function () use ($id) {
        $m = one('SELECT * FROM stock_movements WHERE id = ?', [$id]);
        if (!$m) {
            return null;
        }
        q('DELETE FROM stock_movements WHERE id = ?', [$id]);
        stock_ripple((int)$m['center_id'], (int)$m['product_id'], (string)$m['created_at'], (int)$m['id'], -(int)$m['delta']);
        return $m;
    });
}

/**
 * Insère un mouvement à sa date dans l'historique (centre, article, type, date, note…) et met à jour la suite.
 * $qty : quantité du mouvement (signée), ou quantité comptée pour un inventaire.
 */
function stock_move_insert(array $m, int $qty): int
{
    return tx(function () use ($m, $qty) {
        $cid = (int)$m['center_id'];
        $pid = (int)$m['product_id'];
        $at = (string)$m['created_at'];
        $prev = one('SELECT qty_after FROM stock_movements WHERE center_id = ? AND product_id = ? AND created_at <= ? ORDER BY created_at DESC, id DESC LIMIT 1', [$cid, $pid, $at]);
        if ($prev) {
            $before = (int)$prev['qty_after'];
        } else {
            $next = stock_moves_after($cid, $pid, $at, PHP_INT_MAX)[0] ?? null;
            $before = $next ? max(0, (int)$next['qty_after'] - (int)$next['delta']) : (int)(stock_row($cid, $pid)['qty'] ?? 0);
        }
        $after = max(0, $m['type'] === 'inventaire' ? $qty : $before + $qty);
        $id = insert('stock_movements', [
            'center_id' => $cid, 'product_id' => $pid, 'type' => $m['type'], 'delta' => $after - $before, 'qty_after' => $after,
            'purchase_order_id' => $m['purchase_order_id'] ?? null, 'user_id' => $m['user_id'] ?? null,
            'note' => isset($m['note']) && $m['note'] !== '' ? mb_substr((string)$m['note'], 0, 255) : null, 'created_at' => $at,
        ]);
        if (!stock_row($cid, $pid)) { // article pas encore suivi dans ce centre
            insert('stock', ['center_id' => $cid, 'product_id' => $pid, 'qty' => 0, 'alert_qty' => 0, 'updated_at' => now()]);
            if (!stock_moves_after($cid, $pid, $at, $id)) {
                update('stock', ['qty' => $after], 'center_id = ? AND product_id = ?', [$cid, $pid]);
                return $id;
            }
        }
        stock_ripple($cid, $pid, $at, $id, $after - $before);
        return $id;
    });
}

/**
 * Corrige un mouvement : centre, quantité (positive ; le sens dépend du type, quantité comptée pour un inventaire) et motif.
 * Renvoie l'identifiant du mouvement corrigé.
 */
function stock_move_update(int $id, int $centerId, int $qty, ?string $note): int
{
    return tx(function () use ($id, $centerId, $qty, $note) {
        $m = one('SELECT * FROM stock_movements WHERE id = ?', [$id]);
        if (!$m) {
            throw new RuntimeException('Mouvement introuvable.');
        }
        $qty = abs($qty);
        $isCount = $m['type'] === 'inventaire';
        $signed = $isCount ? $qty : (($m['type'] === 'sortie' || (int)$m['delta'] < 0) ? -$qty : $qty);
        if ($centerId !== (int)$m['center_id']) { // erreur de centre : retiré d'un centre, appliqué à l'autre à la même date
            stock_move_delete($id);
            return stock_move_insert(['center_id' => $centerId, 'note' => $note] + $m, $signed);
        }
        // Même centre : correction sur place, la position dans l'historique est conservée
        $before = (int)$m['qty_after'] - (int)$m['delta'];
        $after = max(0, $isCount ? $signed : $before + $signed);
        update('stock_movements', ['delta' => $after - $before, 'qty_after' => $after, 'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 255) : null], 'id = ?', [$id]);
        stock_ripple((int)$m['center_id'], (int)$m['product_id'], (string)$m['created_at'], $id, $after - (int)$m['qty_after']);
        return $id;
    });
}

/**
 * Rattache le stock d'un article à un autre centre (erreur de centre).
 * Si l'article n'est pas encore suivi dans le centre de destination, le stock et tout son historique sont déplacés ;
 * sinon les quantités sont additionnées par deux mouvements « Transfert ». Renvoie 'moved' ou 'merged'.
 */
function stock_transfer(int $fromCenter, int $toCenter, int $productId): string
{
    return tx(function () use ($fromCenter, $toCenter, $productId) {
        $src = stock_row($fromCenter, $productId);
        if (!$src || $fromCenter === $toCenter) {
            throw new RuntimeException('Rien à transférer.');
        }
        $names = [
            $fromCenter => (string)val('SELECT name FROM centers WHERE id = ?', [$fromCenter]),
            $toCenter => (string)val('SELECT name FROM centers WHERE id = ?', [$toCenter]),
        ];
        $hasHistory = (int)val('SELECT COUNT(*) FROM stock_movements WHERE center_id = ? AND product_id = ?', [$toCenter, $productId]) > 0;
        if (!stock_row($toCenter, $productId) && !$hasHistory) {
            update('stock', ['center_id' => $toCenter, 'updated_at' => now()], 'center_id = ? AND product_id = ?', [$fromCenter, $productId]);
            update('stock_movements', ['center_id' => $toCenter], 'center_id = ? AND product_id = ?', [$fromCenter, $productId]);
            return 'moved';
        }
        $qty = (int)$src['qty'];
        if ($qty > 0) {
            stock_move($fromCenter, $productId, -$qty, 'transfert', 'Transféré vers ' . $names[$toCenter]);
            stock_move($toCenter, $productId, $qty, 'transfert', 'Transféré depuis ' . $names[$fromCenter]);
        }
        $dst = stock_row($toCenter, $productId);
        if ($dst && (int)$dst['alert_qty'] === 0 && (int)$src['alert_qty'] > 0) {
            update('stock', ['alert_qty' => (int)$src['alert_qty']], 'center_id = ? AND product_id = ?', [$toCenter, $productId]);
        }
        q('DELETE FROM stock WHERE center_id = ? AND product_id = ?', [$fromCenter, $productId]);
        return 'merged';
    });
}

// ---------------------------------------------------------------- Budgets

function budget_status(int $centerId, ?int $year = null): array
{
    $year ??= (int)date('Y');
    $b = one('SELECT * FROM budgets WHERE center_id = ? AND year = ?', [$centerId, $year]);
    $from = "$year-01-01 00:00:00";
    $to = ($year + 1) . '-01-01 00:00:00';
    $spent = (float)val("SELECT COALESCE(SUM(l.qty*l.unit_price),0) + COALESCE((SELECT SUM(shipping_fee) FROM purchase_orders WHERE center_id = ? AND status IN ('commande','partiel','recu') AND ordered_at >= ? AND ordered_at < ?),0)
                         FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                         WHERE po.center_id = ? AND po.status IN ('commande','partiel','recu') AND po.ordered_at >= ? AND po.ordered_at < ?",
        [$centerId, $from, $to, $centerId, $from, $to]);
    $committed = 0.0;
    $pending = 0.0;
    if ((int)date('Y') === $year) {
        $committed = (float)val("SELECT COALESCE(SUM(l.qty*l.unit_price),0) FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                                 WHERE po.center_id = ? AND po.status = 'a_commander'", [$centerId]);
        $pending = (float)val("SELECT COALESCE(SUM(qty*unit_price),0) FROM request_lines WHERE center_id = ? AND status = 'pending'", [$centerId]);
    }
    $amount = (float)($b['amount'] ?? 0);
    return [
        'year' => $year, 'amount' => $amount, 'alert_pct' => (int)($b['alert_pct'] ?? 80), 'defined' => $amount > 0,
        'spent' => $spent, 'committed' => $committed, 'pending' => $pending,
        'remaining' => $amount - $spent - $committed,
        'pct' => $amount > 0 ? round($spent / $amount * 100, 1) : 0,
        'pct_committed' => $amount > 0 ? round(($spent + $committed) / $amount * 100, 1) : 0,
        'alert_sent' => (int)($b['alert_sent'] ?? 0),
    ];
}

function budget_level(array $b): string
{
    if (!$b['defined']) {
        return 'none';
    }
    $p = $b['pct_committed'];
    return $p >= 100 ? 'over' : ($p >= $b['alert_pct'] ? 'warn' : 'ok');
}

/** Alerte (une seule fois par an) quand le seuil de budget est franchi. */
function budget_check_alert(int $centerId): void
{
    $b = budget_status($centerId);
    if ($b['defined'] && !$b['alert_sent'] && $b['pct'] >= $b['alert_pct']) {
        update('budgets', ['alert_sent' => 1], 'center_id = ? AND year = ?', [$centerId, $b['year']]);
        $c = one('SELECT name FROM centers WHERE id = ?', [$centerId]);
        notify(admin_ids(), 'budget_alert', 'Budget ' . $c['name'] . ' : ' . $b['pct'] . ' % consommé',
            'Dépenses ' . $b['year'] . ' : ' . money($b['spent']) . ' sur un budget de ' . money($b['amount']) . '.',
            url('admin/budgets'));
    }
}
