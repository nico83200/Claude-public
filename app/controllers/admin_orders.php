<?php
declare(strict_types=1);

/** Demandes en attente, classées par fournisseur et par centre. */
function admin_requests(): void
{
    require_admin();
    $centerFilter = input_int('center');
    $groups = pending_groups($centerFilter ?: null);
    $bySupplier = [];
    foreach ($groups as $g) {
        $bySupplier[$g['supplier_id']]['name'] = $g['supplier_name'];
        $bySupplier[$g['supplier_id']]['color'] = $g['supplier_color'];
        $bySupplier[$g['supplier_id']]['min'] = $g['min_order_amount'];
        $bySupplier[$g['supplier_id']]['groups'][] = $g;
        $bySupplier[$g['supplier_id']]['total'] = ($bySupplier[$g['supplier_id']]['total'] ?? 0) + $g['total'];
    }
    render('admin/requests', [
        'title' => 'Demandes à traiter',
        'bySupplier' => $bySupplier,
        'centers' => all('SELECT id, name FROM centers WHERE active = 1 ORDER BY name'),
        'centerFilter' => $centerFilter,
        'deadlines' => upcoming_deadlines(null, 20, false),
    ]);
}

function admin_refuse_line(): void
{
    require_admin();
    $ids = array_map('intval', (array)($_POST['ids'] ?? [input_int('id')]));
    $reason = mb_substr((string)input('reason', ''), 0, 255) ?: 'Refusée par le service achats';
    $n = 0;
    foreach ($ids as $id) {
        $n += update('request_lines', ['status' => 'cancelled', 'cancel_reason' => $reason], "id = ? AND status = 'pending'", [$id]);
    }
    flash('success', plural($n, 'ligne refusée', 'lignes refusées') . '.');
    redirect_back('admin/requests');
}

function admin_po_create(): void
{
    require_admin();
    $ids = array_map('intval', (array)($_POST['lines'] ?? []));
    try {
        $poId = po_create(input_int('center_id'), input_int('supplier_id'), $ids, (string)input('notes', ''));
        flash('success', 'Bon de commande créé en statut « À commander ».');
        redirect('admin/order', ['id' => $poId]);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/requests');
    }
}

function admin_orders(): void
{
    require_admin();
    $status = (string)input('status', '');
    $center = input_int('center');
    $supplier = input_int('supplier');
    $search = (string)input('q', '');
    $sql = 'SELECT po.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS center_name, c.color AS center_color
            FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id WHERE 1=1';
    $p = [];
    if ($status === 'open') {
        $sql .= " AND po.status IN ('a_commander','commande','partiel')";
    } elseif (isset(PO_STATUSES[$status])) {
        $sql .= ' AND po.status = ?';
        $p[] = $status;
    }
    if ($center) {
        $sql .= ' AND po.center_id = ?';
        $p[] = $center;
    }
    if ($supplier) {
        $sql .= ' AND po.supplier_id = ?';
        $p[] = $supplier;
    }
    if ($search !== '') {
        $sql .= ' AND (po.po_number LIKE ? OR po.supplier_reference LIKE ?)';
        $p[] = "%$search%";
        $p[] = "%$search%";
    }
    $sql .= ' ORDER BY po.created_at DESC LIMIT 300';
    $orders = all($sql, $p);
    foreach ($orders as &$o) {
        $o['totals'] = po_totals((int)$o['id']);
    }
    unset($o);
    $counts = array_column(all('SELECT status, COUNT(*) n FROM purchase_orders GROUP BY status'), 'n', 'status');
    render('admin/orders', [
        'title' => 'Bons de commande', 'orders' => $orders, 'status' => $status, 'center' => $center, 'supplier' => $supplier,
        'q' => $search, 'counts' => $counts,
        'centers' => all('SELECT id, name FROM centers ORDER BY name'),
        'suppliers' => all('SELECT id, name FROM suppliers ORDER BY name'),
    ]);
}

function admin_po_or_fail(int $id): array
{
    $po = one('SELECT po.*, s.name AS supplier_name, s.color AS supplier_color, s.email AS supplier_email, s.phone AS supplier_phone,
                      s.contact_name, s.customer_number, s.min_order_amount, s.free_shipping_from, s.order_method, s.website,
                      c.name AS center_name, c.address AS center_address, c.city AS center_city, c.phone AS center_phone, c.delivery_info,
                      u.first_name AS creator_first, u.last_name AS creator_last
               FROM purchase_orders po
               JOIN suppliers s ON s.id = po.supplier_id
               JOIN centers c ON c.id = po.center_id
               LEFT JOIN users u ON u.id = po.created_by
               WHERE po.id = ?', [$id]);
    if (!$po) {
        abort(404, 'Bon de commande introuvable.');
    }
    return $po;
}

function admin_po_lines(int $poId): array
{
    return all('SELECT l.*, p.image FROM purchase_order_lines l LEFT JOIN products p ON p.id = l.product_id
                WHERE l.purchase_order_id = ? ORDER BY l.label', [$poId]);
}

function admin_order(): void
{
    require_admin();
    $po = admin_po_or_fail(input_int('id'));
    $requesters = [];
    foreach (all('SELECT rl.product_id, rl.qty, rl.comment, u.first_name, u.last_name, u.job, r.urgent FROM request_lines rl
                  JOIN requests r ON r.id = rl.request_id JOIN users u ON u.id = r.user_id
                  WHERE rl.purchase_order_id = ?', [$po['id']]) as $r) {
        $requesters[(int)$r['product_id']][] = $r;
    }
    // Articles du même fournisseur pour ajout manuel
    $supplierProducts = all('SELECT id, name, reference, unit FROM products WHERE supplier_id = ? AND active = 1 ORDER BY name', [$po['supplier_id']]);
    // Autres demandes en attente pour ce couple centre/fournisseur
    $otherPending = (int)val("SELECT COUNT(*) FROM request_lines WHERE status = 'pending' AND center_id = ? AND supplier_id = ?", [$po['center_id'], $po['supplier_id']]);
    render('admin/order', [
        'title' => 'Bon ' . $po['po_number'], 'po' => $po, 'lines' => admin_po_lines((int)$po['id']),
        'totals' => po_totals((int)$po['id']), 'requesters' => $requesters, 'supplierProducts' => $supplierProducts,
        'otherPending' => $otherPending,
        'history' => all('SELECT h.*, u.first_name, u.last_name FROM po_history h LEFT JOIN users u ON u.id = h.user_id
                          WHERE h.purchase_order_id = ? ORDER BY h.created_at DESC, h.id DESC', [$po['id']]),
    ]);
}

/** Modification des lignes (quantités, prix, suppression) tant que le bon n'est pas commandé. */
function admin_order_lines(): void
{
    require_admin();
    $po = admin_po_or_fail(input_int('id'));
    if ($po['status'] !== 'a_commander') {
        flash('error', 'Le bon n\'est plus modifiable (déjà commandé).');
        redirect('admin/order', ['id' => $po['id']]);
    }
    tx(function () use ($po) {
        foreach ((array)($_POST['qty'] ?? []) as $lid => $qty) {
            $lid = (int)$lid;
            $line = one('SELECT * FROM purchase_order_lines WHERE id = ? AND purchase_order_id = ?', [$lid, $po['id']]);
            if (!$line) {
                continue;
            }
            $qty = (int)$qty;
            if ($qty <= 0 || isset($_POST['remove'][$lid])) {
                q('DELETE FROM purchase_order_lines WHERE id = ?', [$lid]);
                // Les demandes liées repassent en attente pour un prochain bon
                if ($line['product_id']) {
                    q("UPDATE request_lines SET status = 'pending', purchase_order_id = NULL WHERE purchase_order_id = ? AND product_id = ?", [$po['id'], $line['product_id']]);
                }
                po_log((int)$po['id'], 'Ligne retirée', $line['label']);
                continue;
            }
            $price = isset($_POST['price'][$lid]) ? max(0, (float)str_replace(',', '.', (string)$_POST['price'][$lid])) : (float)$line['unit_price'];
            if ($qty !== (int)$line['qty'] || abs($price - (float)$line['unit_price']) > 0.001) {
                update('purchase_order_lines', ['qty' => $qty, 'unit_price' => $price], 'id = ?', [$lid]);
                po_log((int)$po['id'], 'Ligne modifiée', $line['label'] . ' : ' . $qty . ' × ' . money($price));
            }
        }
        update('purchase_orders', [
            'notes' => (string)input('notes', '') ?: null,
            'shipping_fee' => input_money('shipping_fee', (float)$po['shipping_fee']),
        ], 'id = ?', [$po['id']]);
    });
    flash('success', 'Bon de commande mis à jour.');
    redirect('admin/order', ['id' => $po['id']]);
}

function admin_order_add_line(): void
{
    require_admin();
    $po = admin_po_or_fail(input_int('id'));
    if ($po['status'] !== 'a_commander') {
        flash('error', 'Le bon n\'est plus modifiable.');
        redirect('admin/order', ['id' => $po['id']]);
    }
    $mode = input('mode');
    if ($mode === 'pending') {
        // Intègre les nouvelles demandes en attente du même centre / fournisseur
        $lines = all("SELECT rl.*, p.name, p.reference, p.unit, p.catalog_price, p.negotiated_price FROM request_lines rl
                      JOIN products p ON p.id = rl.product_id
                      WHERE rl.status = 'pending' AND rl.center_id = ? AND rl.supplier_id = ?", [$po['center_id'], $po['supplier_id']]);
        tx(function () use ($lines, $po) {
            foreach ($lines as $l) {
                po_add_product((int)$po['id'], $l, (int)$l['qty']);
                update('request_lines', ['status' => 'in_po', 'purchase_order_id' => $po['id']], 'id = ?', [$l['id']]);
            }
            po_log((int)$po['id'], 'Demandes ajoutées', count($lines) . ' ligne(s)');
        });
        flash('success', plural(count($lines), 'demande intégrée', 'demandes intégrées') . '.');
    } else {
        $p = one('SELECT * FROM products WHERE id = ? AND supplier_id = ?', [input_int('product_id'), $po['supplier_id']]);
        $qty = max(1, input_int('qty', 1));
        if ($p) {
            po_add_product((int)$po['id'], $p + ['product_id' => $p['id']], $qty);
            po_log((int)$po['id'], 'Ligne ajoutée', $p['name'] . ' × ' . $qty);
            flash('success', 'Article ajouté au bon.');
        }
    }
    po_recompute_shipping((int)$po['id']);
    redirect('admin/order', ['id' => $po['id']]);
}

function po_add_product(int $poId, array $p, int $qty): void
{
    $pid = (int)($p['product_id'] ?? $p['id']);
    $existing = one('SELECT * FROM purchase_order_lines WHERE purchase_order_id = ? AND product_id = ?', [$poId, $pid]);
    if ($existing) {
        update('purchase_order_lines', ['qty' => (int)$existing['qty'] + $qty], 'id = ?', [$existing['id']]);
        return;
    }
    insert('purchase_order_lines', [
        'purchase_order_id' => $poId, 'product_id' => $pid, 'reference' => $p['reference'], 'label' => $p['name'],
        'unit' => $p['unit'], 'qty' => $qty, 'unit_price' => effective_price($p), 'catalog_price' => (float)$p['catalog_price'],
    ]);
}

/** Changement de statut : à commander → commandé → (réception) ; annulation possible. */
function admin_order_status(): void
{
    require_admin();
    $po = admin_po_or_fail(input_int('id'));
    $to = (string)input('to');
    $ok = false;
    switch ($to) {
        case 'commande':
            if ($po['status'] === 'a_commander') {
                if (!po_totals((int)$po['id'])['lines']) {
                    flash('error', 'Le bon est vide.');
                    break;
                }
                update('purchase_orders', [
                    'status' => 'commande', 'ordered_at' => now(), 'ordered_by' => user()['id'],
                    'supplier_reference' => (string)input('supplier_reference', '') ?: null,
                    'expected_date' => input('expected_date') ?: null,
                ], 'id = ?', [$po['id']]);
                po_log((int)$po['id'], 'Commandé', trim('Réf. fournisseur : ' . (input('supplier_reference') ?: '—')));
                $ok = true;
            }
            break;
        case 'a_commander':
            if ($po['status'] === 'commande' && !po_totals((int)$po['id'])['received']) {
                update('purchase_orders', ['status' => 'a_commander', 'ordered_at' => null, 'ordered_by' => null], 'id = ?', [$po['id']]);
                po_log((int)$po['id'], 'Retour à « À commander »');
                $ok = true;
            }
            break;
        case 'annule':
            if (in_array($po['status'], ['a_commander', 'commande'], true)) {
                tx(function () use ($po) {
                    update('purchase_orders', ['status' => 'annule'], 'id = ?', [$po['id']]);
                    if (input('requeue') === '1') {
                        q("UPDATE request_lines SET status = 'pending', purchase_order_id = NULL WHERE purchase_order_id = ?", [$po['id']]);
                    }
                    po_log((int)$po['id'], 'Annulé', input('requeue') === '1' ? 'Demandes remises en attente' : null);
                });
                $ok = true;
            }
            break;
        case 'recu':
            if (in_array($po['status'], ['commande', 'partiel'], true)) {
                q('UPDATE purchase_order_lines SET qty_received = qty, received_at = ?, received_by = ? WHERE purchase_order_id = ? AND qty_received < qty',
                    [now(), user()['id'], $po['id']]);
                po_log((int)$po['id'], 'Réception totale (administrateur)');
                po_refresh_reception_status((int)$po['id']);
                $ok = true;
            }
            break;
        case 'update_ref':
            update('purchase_orders', [
                'supplier_reference' => (string)input('supplier_reference', '') ?: null,
                'expected_date' => input('expected_date') ?: null,
            ], 'id = ?', [$po['id']]);
            $ok = true;
            break;
    }
    flash($ok ? 'success' : 'error', $ok ? 'Bon de commande mis à jour.' : 'Action impossible dans le statut actuel.');
    redirect('admin/order', ['id' => $po['id']]);
}

function admin_order_print(): void
{
    require_admin();
    $po = admin_po_or_fail(input_int('id'));
    render('admin/order_print', [
        'po' => $po, 'lines' => admin_po_lines((int)$po['id']), 'totals' => po_totals((int)$po['id']),
    ], null);
}

function admin_order_csv(): void
{
    require_admin();
    $po = admin_po_or_fail(input_int('id'));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $po['po_number'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Bon', 'Centre', 'Fournisseur', 'Référence', 'Désignation', 'Conditionnement', 'Quantité', 'Prix unitaire HT', 'Total HT', 'Reçu'], ';', '"', '');
    foreach (admin_po_lines((int)$po['id']) as $l) {
        fputcsv($out, [
            $po['po_number'], $po['center_name'], $po['supplier_name'], $l['reference'], $l['label'], $l['unit'], $l['qty'],
            number_format((float)$l['unit_price'], 2, ',', ''), number_format($l['qty'] * (float)$l['unit_price'], 2, ',', ''), $l['qty_received'],
        ], ';', '"', '');
    }
    fclose($out);
    exit;
}
