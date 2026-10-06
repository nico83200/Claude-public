<?php
declare(strict_types=1);

function user_dashboard(): void
{
    $u = require_login();
    $center = require_center();
    $cid = (int)$center['id'];

    $toReceive = all("SELECT po.*, s.name AS supplier_name, s.color AS supplier_color
                      FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                      WHERE po.center_id = ? AND po.status IN ('commande','partiel')
                      ORDER BY po.ordered_at", [$cid]);

    $myPending = (int)val("SELECT COUNT(*) FROM request_lines rl JOIN requests r ON r.id = rl.request_id
                           WHERE r.user_id = ? AND rl.center_id = ? AND rl.status = 'pending'", [$u['id'], $cid]);
    $centerPending = (int)val("SELECT COUNT(*) FROM request_lines WHERE center_id = ? AND status = 'pending'", [$cid]);
    $inProgress = (int)val("SELECT COUNT(*) FROM purchase_orders WHERE center_id = ? AND status = 'a_commander'", [$cid]);

    $recent = all('SELECT r.*, u.first_name, u.last_name,
                          (SELECT COUNT(*) FROM request_lines rl WHERE rl.request_id = r.id) AS nb_lines,
                          (SELECT COALESCE(SUM(qty*unit_price),0) FROM request_lines rl WHERE rl.request_id = r.id AND rl.status <> \'cancelled\') AS total
                   FROM requests r JOIN users u ON u.id = r.user_id
                   WHERE r.center_id = ? ORDER BY r.created_at DESC LIMIT 6', [$cid]);
    foreach ($recent as &$r) {
        $r['lines'] = all('SELECT rl.*, p.name, po.status AS po_status FROM request_lines rl
                           JOIN products p ON p.id = rl.product_id
                           LEFT JOIN purchase_orders po ON po.id = rl.purchase_order_id
                           WHERE rl.request_id = ?', [$r['id']]);
    }
    unset($r);

    // Raccourcis : favoris puis articles les plus demandés dans le centre
    $favIds = user_favorites((int)$u['id']);
    $pop = product_popularity($cid);
    arsort($pop);
    $quickIds = array_slice(array_values(array_unique(array_merge($favIds, array_keys($pop)))), 0, 8);
    $quick = $quickIds ? catalog_products($cid, ['ids' => $quickIds]) : [];
    usort($quick, fn($a, $b) => array_search((int)$a['id'], $quickIds) <=> array_search((int)$b['id'], $quickIds));

    $monthSpend = (float)val("SELECT COALESCE(SUM(l.qty*l.unit_price),0) FROM purchase_orders po
                              JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                              WHERE po.center_id = ? AND po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?",
                              [$cid, date('Y-m-01 00:00:00')]);

    render('user/dashboard', [
        'title' => 'Tableau de bord',
        'center' => $center,
        'deadlines' => upcoming_deadlines($cid, 6),
        'toReceive' => $toReceive,
        'myPending' => $myPending,
        'centerPending' => $centerPending,
        'inProgress' => $inProgress,
        'recent' => $recent,
        'quick' => $quick,
        'favIds' => $favIds,
        'cartCount' => cart_count((int)$u['id'], $cid),
        'monthSpend' => $monthSpend,
    ]);
}

function user_requests(): void
{
    $u = require_login();
    $center = require_center();
    $scope = input('scope', 'mine') === 'center' ? 'center' : 'mine';
    $status = (string)input('status', '');

    $sql = 'SELECT r.*, u.first_name, u.last_name, u.job FROM requests r JOIN users u ON u.id = r.user_id WHERE r.center_id = ?';
    $params = [$center['id']];
    if ($scope === 'mine') {
        $sql .= ' AND r.user_id = ?';
        $params[] = $u['id'];
    }
    $sql .= ' ORDER BY r.created_at DESC LIMIT 100';
    $requests = all($sql, $params);
    foreach ($requests as $i => &$r) {
        $r['lines'] = all('SELECT rl.*, p.name, p.reference, p.unit, p.image, s.name AS supplier_name, s.color AS supplier_color,
                                  po.status AS po_status, po.po_number, pol.qty_received
                           FROM request_lines rl
                           JOIN products p ON p.id = rl.product_id
                           JOIN suppliers s ON s.id = rl.supplier_id
                           LEFT JOIN purchase_orders po ON po.id = rl.purchase_order_id
                           LEFT JOIN purchase_order_lines pol ON pol.purchase_order_id = rl.purchase_order_id AND pol.product_id = rl.product_id
                           WHERE rl.request_id = ? ORDER BY s.name, p.name', [$r['id']]);
        if ($status !== '') {
            $r['lines'] = array_values(array_filter($r['lines'], function ($l) use ($status) {
                $ps = $l['po_status'] ?? null;
                return match ($status) {
                    'pending' => $l['status'] === 'pending' || ($l['status'] === 'in_po' && $ps === 'a_commander'),
                    'ordered' => $l['status'] === 'in_po' && in_array($ps, ['commande', 'partiel'], true),
                    'received' => $l['status'] === 'in_po' && $ps === 'recu',
                    default => true,
                };
            }));
            if (!$r['lines']) {
                unset($requests[$i]);
            }
        }
    }
    unset($r);

    render('user/requests', [
        'title' => 'Suivi des demandes', 'center' => $center, 'requests' => array_values($requests),
        'scope' => $scope, 'status' => $status,
    ]);
}

function user_cancel_line(): void
{
    $u = require_login();
    $line = one('SELECT rl.*, r.user_id FROM request_lines rl JOIN requests r ON r.id = rl.request_id WHERE rl.id = ?', [input_int('id')]);
    if (!$line || !can_access_center((int)$line['center_id']) || ((int)$line['user_id'] !== (int)$u['id'] && !is_admin())) {
        abort(403);
    }
    if ($line['status'] !== 'pending') {
        flash('error', 'Cette ligne est déjà prise en charge par le service achats et ne peut plus être annulée.');
    } else {
        update('request_lines', ['status' => 'cancelled', 'cancel_reason' => 'Annulée par le demandeur'], 'id = ?', [$line['id']]);
        flash('success', 'Ligne annulée.');
    }
    redirect_back('requests');
}

/** Remet dans le panier les articles d'une ancienne demande. */
function user_reorder(): void
{
    $u = require_login();
    $req = one('SELECT * FROM requests WHERE id = ?', [input_int('id')]);
    if (!$req || !can_access_center((int)$req['center_id'])) {
        abort(403);
    }
    $n = 0;
    foreach (all('SELECT product_id, qty FROM request_lines WHERE request_id = ?', [$req['id']]) as $l) {
        if (product_visible_for_center((int)$l['product_id'], (int)$req['center_id'])) {
            cart_add((int)$u['id'], (int)$req['center_id'], (int)$l['product_id'], (int)$l['qty']);
            $n++;
        }
    }
    flash($n ? 'success' : 'error', $n ? plural($n, 'article ajouté', 'articles ajoutés') . ' au panier.' : 'Aucun article de cette demande n\'est encore disponible.');
    redirect('cart', ['c' => $req['center_id']]);
}
