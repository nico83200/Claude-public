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
