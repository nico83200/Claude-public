<?php
declare(strict_types=1);

function stock_index(): void
{
    require_login();
    $center = require_center();
    $filter = (string)input('filter', '');
    $items = stock_list((int)$center['id'], $filter);
    $tracked = array_map('intval', array_column(stock_list((int)$center['id']), 'product_id'));
    $value = 0.0;
    foreach ($items as $it) {
        $value += (int)$it['qty'] * effective_price($it);
    }
    render('user/stock', [
        'title' => 'Inventaire', 'center' => $center, 'items' => $items, 'filter' => $filter,
        'lowCount' => stock_low_count((int)$center['id']),
        'value' => $value,
        'catalog' => array_values(array_filter(catalog_products((int)$center['id']), fn($p) => !in_array((int)$p['id'], $tracked, true))),
        'lastCount' => val('SELECT MAX(counted_at) FROM stock WHERE center_id = ?', [$center['id']]),
    ]);
}

/** Enregistre un inventaire (quantités comptées) et les seuils d'alerte. */
function stock_save(): void
{
    require_login();
    $center = require_center();
    $cid = (int)$center['id'];
    $note = mb_substr((string)input('note', ''), 0, 200) ?: null;
    $n = 0;
    tx(function () use ($cid, $note, &$n) {
        foreach ((array)($_POST['alert'] ?? []) as $pid => $alert) {
            if (is_numeric($alert) && stock_row($cid, (int)$pid)) {
                update('stock', ['alert_qty' => max(0, (int)$alert)], 'center_id = ? AND product_id = ?', [$cid, (int)$pid]);
            }
        }
        foreach ((array)($_POST['counted'] ?? []) as $pid => $counted) {
            if ($counted === '' || !is_numeric($counted) || !stock_row($cid, (int)$pid)) {
                continue; // champ laissé vide : article non compté
            }
            stock_count($cid, (int)$pid, (int)$counted, $note);
            $n++;
        }
    });
    flash('success', $n ? 'Inventaire enregistré : ' . plural($n, 'article compté', 'articles comptés') . '.' : 'Seuils d\'alerte enregistrés.');
    redirect('stock', ['filter' => input('filter') ?: null]);
}

/** Sortie de stock (consommation réelle) ou entrée manuelle. */
function stock_exit(): void
{
    require_login();
    $center = require_center();
    $pid = input_int('product_id');
    $qty = input_int('qty');
    $mode = input('mode') === 'in' ? 'ajout' : 'sortie';
    if (!$pid || $qty <= 0 || !product_visible_for_center($pid, (int)$center['id'])) {
        flash('error', 'Article ou quantité invalide.');
        redirect('stock');
    }
    $after = stock_move((int)$center['id'], $pid, $mode === 'sortie' ? -$qty : $qty, $mode, (string)input('note', '') ?: null);
    $name = (string)val('SELECT name FROM products WHERE id = ?', [$pid]);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['ok' => true, 'qty' => $after, 'name' => $name]);
    }
    flash('success', ($mode === 'sortie' ? 'Sortie' : 'Entrée') . ' de ' . $qty . ' × ' . $name . ' enregistrée. Stock restant : ' . $after . '.');
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['ok' => true, 'qty' => $after]);
    }
    redirect_back('stock');
}

/** Ajoute des articles au suivi de stock du centre. */
function stock_add(): void
{
    require_login();
    $center = require_center();
    $cid = (int)$center['id'];
    $ids = array_map('intval', (array)($_POST['product_ids'] ?? [input_int('product_id')]));
    $qty = max(0, input_int('qty'));
    $alert = max(0, input_int('alert'));
    $n = 0;
    foreach (array_filter($ids) as $pid) {
        if (!product_visible_for_center($pid, $cid) || stock_row($cid, $pid)) {
            continue;
        }
        stock_set_alert($cid, $pid, $alert);
        if ($qty > 0) {
            stock_count($cid, $pid, $qty, 'Stock initial');
        }
        $n++;
    }
    flash($n ? 'success' : 'info', $n ? plural($n, 'article ajouté', 'articles ajoutés') . ' au suivi de stock.' : 'Article déjà suivi.');
    redirect('stock');
}

function stock_remove(): void
{
    require_login();
    $center = require_center();
    q('DELETE FROM stock WHERE center_id = ? AND product_id = ?', [$center['id'], input_int('product_id')]);
    flash('success', 'Article retiré du suivi de stock (l\'historique est conservé).');
    redirect('stock');
}

function stock_history(): void
{
    require_login();
    $center = require_center();
    $pid = input_int('product_id');
    $sql = 'SELECT m.*, p.name, p.unit, u.first_name, u.last_name FROM stock_movements m
            JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id WHERE m.center_id = ?';
    $params = [$center['id']];
    if ($pid) {
        $sql .= ' AND m.product_id = ?';
        $params[] = $pid;
    }
    $sql .= ' ORDER BY m.created_at DESC, m.id DESC LIMIT 300';
    $product = $pid ? one('SELECT p.*, st.qty, st.alert_qty FROM products p LEFT JOIN stock st ON st.product_id = p.id AND st.center_id = ? WHERE p.id = ?', [$center['id'], $pid]) : null;
    render('user/stock_history', ['title' => 'Mouvements de stock', 'center' => $center, 'moves' => all($sql, $params), 'product' => $product]);
}

/** Recherche d'un article par code-barres (scan caméra ou douchette). */
function api_barcode(): void
{
    $u = require_login();
    $center = current_center();
    $code = preg_replace('/\s+/', '', (string)input('code', ''));
    if (!$center || $code === '') {
        json_response(['found' => false]);
    }
    $p = one('SELECT p.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
              FROM products p JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id
              WHERE (p.barcode = ? OR p.reference = ?) AND p.active = 1 AND ' . supplier_visible_sql('s') . ' ORDER BY (p.barcode = ?) DESC LIMIT 1',
        [$code, $code, $center['id'], $code]);
    if (!$p) {
        json_response(['found' => false, 'code' => $code]);
    }
    require_once APP . '/controllers/catalog.php';
    $st = stock_row((int)$center['id'], (int)$p['id']);
    json_response(['found' => true, 'code' => $code, 'product' => product_json($p, user_favorites((int)$u['id'])),
        'stock' => $st ? ['qty' => (int)$st['qty'], 'alert' => (int)$st['alert_qty']] : null]);
}

// ---------------------------------------------------------------- Notifications

function notifications_index(): void
{
    $u = require_login();
    $items = all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', [$u['id']]);
    q('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), $u['id']]);
    render('user/notifications', ['title' => 'Notifications', 'items' => $items]);
}

function notifications_open(): void
{
    $u = require_login();
    $n = one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [input_int('id'), $u['id']]);
    if (!$n) {
        redirect('notifications');
    }
    update('notifications', ['read_at' => now()], 'id = ?', [$n['id']]);
    $link = (string)$n['link'];
    header('Location: ' . (str_starts_with($link, 'index.php?') ? $link : 'index.php'));
    exit;
}

function api_notifications(): void
{
    $u = require_login();
    json_response([
        'unread' => unread_notifications((int)$u['id']),
        'items' => array_map(fn($n) => [
            'id' => (int)$n['id'], 'title' => $n['title'], 'body' => mb_substr((string)$n['body'], 0, 140),
            'type' => $n['type'], 'read' => $n['read_at'] !== null, 'when' => date_fr($n['created_at'], true),
            'url' => url('notifications/open', ['id' => $n['id']]),
        ], all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8', [$u['id']])),
    ]);
}
