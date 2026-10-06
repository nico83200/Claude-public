<?php
declare(strict_types=1);

function reception_index(): void
{
    require_login();
    $center = require_center();
    $open = all("SELECT po.*, s.name AS supplier_name, s.color AS supplier_color, s.delivery_delay
                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                 WHERE po.center_id = ? AND po.status IN ('commande','partiel') ORDER BY po.ordered_at", [$center['id']]);
    foreach ($open as &$po) {
        $po['totals'] = po_totals((int)$po['id']);
    }
    unset($po);
    $done = all("SELECT po.*, s.name AS supplier_name, s.color AS supplier_color
                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                 WHERE po.center_id = ? AND po.status = 'recu' ORDER BY po.received_at DESC LIMIT 15", [$center['id']]);
    $upcoming = all("SELECT po.*, s.name AS supplier_name, s.color AS supplier_color
                 FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id
                 WHERE po.center_id = ? AND po.status = 'a_commander' ORDER BY po.created_at", [$center['id']]);
    render('user/receptions', ['title' => 'Réceptions', 'center' => $center, 'open' => $open, 'done' => $done, 'upcoming' => $upcoming]);
}

function reception_po_or_fail(int $id): array
{
    $po = one('SELECT po.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS center_name
               FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id
               WHERE po.id = ?', [$id]);
    if (!$po || !can_access_center((int)$po['center_id'])) {
        abort(404, 'Bon de commande introuvable.');
    }
    return $po;
}

function reception_view(): void
{
    require_login();
    $po = reception_po_or_fail(input_int('id'));
    $_SESSION['center_id'] = (int)$po['center_id'];
    $lines = all('SELECT l.*, p.image, u.first_name, u.last_name FROM purchase_order_lines l
                  LEFT JOIN products p ON p.id = l.product_id
                  LEFT JOIN users u ON u.id = l.received_by
                  WHERE l.purchase_order_id = ? ORDER BY l.label', [$po['id']]);
    // Demandeurs de chaque article (pour savoir à qui remettre le colis)
    $requesters = [];
    foreach (all('SELECT rl.product_id, rl.qty, u.first_name, u.last_name, u.job FROM request_lines rl
                  JOIN requests r ON r.id = rl.request_id JOIN users u ON u.id = r.user_id
                  WHERE rl.purchase_order_id = ?', [$po['id']]) as $r) {
        $requesters[(int)$r['product_id']][] = $r;
    }
    render('user/reception', [
        'title' => 'Réception ' . $po['po_number'], 'center' => current_center(), 'po' => $po, 'lines' => $lines,
        'requesters' => $requesters, 'totals' => po_totals((int)$po['id']),
        'history' => all('SELECT h.*, u.first_name, u.last_name FROM po_history h LEFT JOIN users u ON u.id = h.user_id
                          WHERE h.purchase_order_id = ? ORDER BY h.created_at DESC, h.id DESC', [$po['id']]),
    ]);
}

function reception_save(): void
{
    $u = require_login();
    $po = reception_po_or_fail(input_int('id'));
    if (!in_array($po['status'], ['commande', 'partiel', 'recu'], true)) {
        flash('error', 'Ce bon n\'est pas encore passé en statut « Commandé ».');
        redirect('reception', ['id' => $po['id']]);
    }
    $all = input('all') === '1';
    // received[id] = quantité reçue (la case à cocher remplit automatiquement la quantité totale)
    $received = (array)($_POST['received'] ?? []);
    $changes = [];
    tx(function () use ($po, $u, $all, $received, &$changes) {
        foreach (all('SELECT * FROM purchase_order_lines WHERE purchase_order_id = ?', [$po['id']]) as $l) {
            $id = (int)$l['id'];
            if ($all) {
                $qty = (int)$l['qty'];
            } elseif (isset($received[$id]) && is_numeric($received[$id])) {
                $qty = max(0, min((int)$l['qty'], (int)$received[$id]));
            } else {
                continue;
            }
            if ($qty !== (int)$l['qty_received']) {
                update('purchase_order_lines', [
                    'qty_received' => $qty,
                    'received_at' => $qty > 0 ? now() : null,
                    'received_by' => $qty > 0 ? $u['id'] : null,
                ], 'id = ?', [$id]);
                stock_from_reception($po, $l, (int)$l['qty_received'], $qty);
                $changes[] = $l['label'] . ' : ' . $qty . '/' . $l['qty'];
            }
        }
        if ($changes) {
            po_log((int)$po['id'], 'Réception', implode(' · ', $changes));
        }
    });
    $status = po_refresh_reception_status((int)$po['id']);
    if ($changes && in_array($status, ['partiel', 'recu'], true)) {
        notify_po((int)$po['id'], 'po_received');
    }
    if (!$changes) {
        flash('info', 'Aucune modification.');
    } else {
        flash('success', ($status === 'recu' ? 'Commande entièrement reçue. Merci !' : 'Réception enregistrée (livraison partielle).') . ' Le stock du centre a été mis à jour.');
    }
    redirect('reception', ['id' => $po['id']]);
}
