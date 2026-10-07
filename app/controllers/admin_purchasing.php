<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_orders.php';
require_once __DIR__ . '/admin_tools.php';

// ---------------------------------------------------------------- Comparateur fournisseurs

function admin_compare(): void
{
    require_admin();
    // Groupes : même groupe d'équivalence ou même code-barres chez plusieurs fournisseurs
    $products = all('SELECT p.*, s.name AS supplier_name, s.color AS supplier_color, s.min_order_amount
                     FROM products p JOIN suppliers s ON s.id = p.supplier_id WHERE p.active = 1 AND s.active = 1
                       AND ((p.compare_group IS NOT NULL AND p.compare_group <> \'\') OR (p.barcode IS NOT NULL AND p.barcode <> \'\'))');
    $groups = [];
    foreach ($products as $p) {
        $key = $p['compare_group'] ? 'g:' . mb_strtolower($p['compare_group']) : 'b:' . $p['barcode'];
        $groups[$key][] = $p;
    }
    $groups = array_filter($groups, fn($g) => count(array_unique(array_column($g, 'supplier_id'))) > 1);
    $rows = [];
    $potential = 0.0;
    $since = date('Y-m-d H:i:s', strtotime('-12 months'));
    foreach ($groups as $key => $g) {
        usort($g, fn($a, $b) => effective_price($a) <=> effective_price($b));
        $best = effective_price($g[0]);
        foreach ($g as &$p) {
            $p['price'] = effective_price($p);
            $p['qty12'] = (int)val("SELECT COALESCE(SUM(l.qty),0) FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id
                                     WHERE l.product_id = ? AND po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?", [$p['id'], $since]);
            $p['overspend'] = ($p['price'] - $best) * $p['qty12'];
            $potential += $p['overspend'];
        }
        unset($p);
        $rows[] = ['key' => $key, 'label' => $g[0]['compare_group'] ?: ('EAN ' . $g[0]['barcode']), 'items' => $g];
    }
    usort($rows, fn($a, $b) => array_sum(array_column($b['items'], 'overspend')) <=> array_sum(array_column($a['items'], 'overspend')));
    render('admin/compare', ['title' => 'Comparateur fournisseurs', 'rows' => $rows, 'potential' => $potential,
        'ungrouped' => (int)val("SELECT COUNT(*) FROM products WHERE active = 1 AND (compare_group IS NULL OR compare_group = '')")]);
}

/** Bascule une ligne de demande vers un article équivalent (moins cher). */
function admin_request_switch(): void
{
    require_admin();
    $line = one("SELECT rl.*, p.name, p.compare_group, p.barcode FROM request_lines rl JOIN products p ON p.id = rl.product_id WHERE rl.id = ? AND rl.status = 'pending'", [input_int('line_id')]);
    $target = $line ? one('SELECT * FROM products WHERE id = ? AND active = 1', [input_int('product_id')]) : null;
    if (!$line || !$target || !in_array((int)$target['id'], array_map('intval', array_column(product_equivalents(['id' => $line['product_id']] + $line, (int)$line['center_id']), 'id')), true)) {
        flash('error', 'Bascule impossible.');
        redirect('admin/requests');
    }
    switch_request_line($line, $target);
    flash('success', '« ' . $line['name'] . ' » remplacé par l\'équivalent « ' . $target['name'] . ' ».');
    redirect_back('admin/requests');
}

function switch_request_line(array $line, array $target): void
{
    update('request_lines', ['product_id' => $target['id'], 'supplier_id' => $target['supplier_id'], 'unit_price' => effective_price($target),
        'comment' => trim(($line['comment'] ? $line['comment'] . ' · ' : '') . 'équivalent moins cher')], 'id = ?', [$line['id']]);
    audit('Demande basculée vers un équivalent', 'request_line', (int)$line['id'], $line['name'] . ' → ' . $target['name']);
}

/** Bascule toutes les lignes en attente vers l'équivalent le moins cher. */
function admin_requests_optimize(): void
{
    require_admin();
    $n = 0;
    $saving = 0.0;
    tx(function () use (&$n, &$saving) {
        foreach (all("SELECT rl.*, p.name, p.compare_group, p.barcode, p.catalog_price, p.negotiated_price FROM request_lines rl JOIN products p ON p.id = rl.product_id WHERE rl.status = 'pending'") as $l) {
            $better = cheaper_equivalent(['id' => $l['product_id']] + $l, (int)$l['center_id']);
            if ($better) {
                switch_request_line($l, $better);
                $saving += $better['saving'] * (int)$l['qty'];
                $n++;
            }
        }
    });
    flash($n ? 'success' : 'info', $n ? plural($n, 'ligne basculée', 'lignes basculées') . ' vers un équivalent moins cher — économie estimée ' . money($saving) . '.' : 'Toutes les demandes sont déjà au meilleur prix connu.');
    redirect('admin/requests');
}

// ---------------------------------------------------------------- Commandes groupées

function admin_po_create_group(): void
{
    require_admin();
    try {
        $ref = po_create_group(input_int('supplier_id'), array_map('intval', (array)($_POST['lines'] ?? [])), (string)input('notes', ''));
        foreach (all('SELECT id FROM purchase_orders WHERE group_ref = ?', [$ref]) as $po) {
            notify_po((int)$po['id'], 'po_created');
        }
        audit('Commande groupée créée', 'group', null, $ref);
        flash('success', 'Commande groupée ' . $ref . ' créée (un bon par centre).');
        redirect('admin/order-group', ['ref' => $ref, 'created' => 1]);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/requests');
    }
}

function group_or_fail(string $ref): array
{
    $pos = all('SELECT po.*, c.name AS center_name, c.color AS center_color, s.name AS supplier_name, s.email AS supplier_email, s.min_order_amount, s.free_shipping_from,
                       s.order_method, s.website, s.order_url, s.order_note, s.customer_number, s.phone AS supplier_phone, s.contact_name
                FROM purchase_orders po JOIN centers c ON c.id = po.center_id JOIN suppliers s ON s.id = po.supplier_id WHERE po.group_ref = ? ORDER BY c.name', [$ref]);
    if (!$pos) {
        abort(404, 'Commande groupée introuvable.');
    }
    return $pos;
}

function admin_order_group(): void
{
    require_admin();
    $ref = (string)input('ref');
    $pos = group_or_fail($ref);
    $total = 0.0;
    foreach ($pos as &$po) {
        $po['totals'] = po_totals((int)$po['id']);
        $total += $po['totals']['total'];
    }
    unset($po);
    $copy = [];
    foreach ($pos as $po) {
        foreach (all('SELECT reference, qty, label FROM purchase_order_lines WHERE purchase_order_id = ? ORDER BY label', [$po['id']]) as $l) {
            $copy[] = trim(($l['reference'] ?: '') . "\t" . $l['qty'] . "\t" . $l['label'] . "\t" . $po['center_name']);
        }
    }
    render('admin/order_group', ['title' => 'Commande groupée ' . $ref, 'ref' => $ref, 'pos' => $pos, 'total' => $total, 'copyText' => implode("\n", $copy),
        'shipping' => array_sum(array_map(fn($p) => (float)$p['shipping_fee'], $pos))]);
}

function admin_order_group_ordered(): void
{
    require_admin();
    $ref = (string)input('ref');
    $n = 0;
    foreach (group_or_fail($ref) as $po) {
        if ($po['status'] === 'a_commander' && po_totals((int)$po['id'])['lines']) {
            po_mark_ordered($po, (string)input('supplier_reference', '') ?: $ref, input('expected_date') ?: null);
            $n++;
        }
    }
    flash('success', plural($n, 'bon passé', 'bons passés') . ' en « Commandé ».');
    redirect('admin/order-group', ['ref' => $ref]);
}

// ---------------------------------------------------------------- PDF et envoi au fournisseur

function po_ids_from_input(): array
{
    if ($ref = (string)input('ref', '')) {
        return array_map('intval', array_column(group_or_fail($ref), 'id'));
    }
    return [input_int('id')];
}

function admin_order_pdf(): void
{
    require_admin();
    $ids = po_ids_from_input();
    $first = one('SELECT po_number, group_ref FROM purchase_orders WHERE id = ?', [$ids[0]]) ?? abort(404);
    $pdf = po_pdf($ids);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdf));
    header('Content-Disposition: ' . (input('dl') ? 'attachment' : 'inline') . '; filename="' . (input('ref') ? $first['group_ref'] : $first['po_number']) . '.pdf"');
    echo $pdf;
    exit;
}

/** Envoie le bon (PDF joint) à l'adresse de commande du fournisseur, et peut le passer en « Commandé ». */
function admin_order_send(): void
{
    require_admin();
    $ids = po_ids_from_input();
    $pos = array_map(fn($id) => one('SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.contact_name, s.customer_number FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?', [$id]), $ids);
    $first = $pos[0] ?? null;
    $back = input('ref') ? ['admin/order-group', ['ref' => input('ref')]] : ['admin/order', ['id' => $ids[0]]];
    if (!mail_case_enabled('supplier_po')) {
        flash('error', 'L\'envoi des bons aux fournisseurs par e-mail est désactivé (Paramètres → Notifications & e-mails).');
        redirect(...$back);
    }
    $to = trim((string)input('to', '')) ?: (string)($first['supplier_email'] ?? '');
    if (!$first || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Adresse e-mail du fournisseur manquante ou invalide (renseignez-la dans la fiche fournisseur).');
        redirect(...$back);
    }
    $dir = ROOT . '/storage/mail';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $draft = po_mail_draft($ids, (string)input('message', ''));
    $label = $draft['label'];
    $path = $dir . '/' . $label . '-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, po_pdf($ids));
    $me = user();
    $message = $draft['body'];
    $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1e2335;line-height:1.5">' . nl2br(e($message)) . '</div>';
    send_mail($to, $draft['subject'], $html,
        [['path' => $path, 'name' => $label . '.pdf', 'type' => 'application/pdf']]);
    if (input('cc_me') === '1' && mail_case_enabled('supplier_copy')) {
        send_mail((string)$me['email'], '[Copie] Commande ' . $label, $html, [['path' => $path, 'name' => $label . '.pdf', 'type' => 'application/pdf']]);
    }
    foreach ($pos as $po) {
        update('purchase_orders', ['sent_to_supplier_at' => now()], 'id = ?', [$po['id']]);
        po_log((int)$po['id'], 'Envoyé au fournisseur', $to);
        if (input('mark_ordered') === '1' && $po['status'] === 'a_commander') {
            po_mark_ordered($po, (string)($po['supplier_reference'] ?? ''), input('expected_date') ?: null);
        }
    }
    audit('Bon envoyé au fournisseur', 'purchase_order', (int)$ids[0], $label . ' → ' . $to);
    flash('success', 'Bon ' . $label . ' envoyé à ' . $to . ' (PDF joint).');
    redirect(...$back);
}

/** Brouillon d'e-mail (.eml) avec le PDF joint, à ouvrir dans la messagerie de l'ordinateur. */
function admin_order_eml(): void
{
    require_admin();
    $ids = po_ids_from_input();
    $eml = po_eml($ids);
    $d = po_mail_draft($ids);
    foreach ($ids as $id) {
        po_log($id, 'E-mail préparé', 'brouillon avec PDF pour la messagerie de l\'ordinateur');
    }
    header('Content-Type: message/rfc822');
    header('Content-Length: ' . strlen($eml));
    header('Content-Disposition: attachment; filename="' . $d['label'] . '.eml"');
    echo $eml;
    exit;
}

// ---------------------------------------------------------------- Factures

function admin_order_invoice(): void
{
    require_admin();
    $po = one('SELECT * FROM purchase_orders WHERE id = ?', [input_int('id')]) ?? abort(404);
    try {
        $file = handle_invoice_upload('invoice_file');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/order', ['id' => $po['id']]);
    }
    $data = [
        'invoice_number' => mb_substr(trim((string)input('invoice_number', '')), 0, 80) ?: null,
        'invoice_date' => input('invoice_date') ?: null,
        'invoice_amount' => input_money('invoice_amount', null),
    ];
    if ($file) {
        if ($po['invoice_file']) {
            @unlink(invoices_dir() . '/' . basename($po['invoice_file']));
        }
        $data['invoice_file'] = $file;
    }
    $check = invoice_check(array_merge($po, $data));
    $data['invoice_status'] = $check['status'] === 'none' ? null : $check['status'];
    update('purchase_orders', $data, 'id = ?', [$po['id']]);
    po_log((int)$po['id'], 'Facture saisie', trim(($data['invoice_number'] ?? '') . ' ' . ($data['invoice_amount'] !== null ? money($data['invoice_amount']) : '')));
    audit('Facture saisie', 'purchase_order', (int)$po['id'], $data);
    flash($check['status'] === 'ecart' ? 'error' : 'success', $check['status'] === 'ecart'
        ? 'Facture enregistrée avec un écart de ' . money($check['diff']) . ' par rapport aux marchandises reçues (' . money($check['expected']) . ').'
        : 'Facture enregistrée et rapprochée.');
    redirect('admin/order', ['id' => $po['id']]);
}

function admin_order_invoice_file(): void
{
    require_admin();
    $po = one('SELECT invoice_file, po_number FROM purchase_orders WHERE id = ?', [input_int('id')]);
    $path = $po && $po['invoice_file'] ? invoices_dir() . '/' . basename($po['invoice_file']) : null;
    if (!$path || !is_file($path)) {
        abort(404);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="facture-' . $po['po_number'] . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    readfile($path);
    exit;
}

function admin_invoices(): void
{
    require_admin();
    $filter = (string)input('filter', 'todo');
    $sql = "SELECT po.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS center_name FROM purchase_orders po
            JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id WHERE po.status IN ('commande','partiel','recu')";
    $sql .= match ($filter) {
        'todo' => " AND po.invoice_amount IS NULL AND po.status IN ('partiel','recu')",
        'ecart' => " AND po.invoice_status = 'ecart'",
        'ok' => " AND po.invoice_status = 'ok'",
        default => '',
    };
    $orders = all($sql . ' ORDER BY po.ordered_at DESC LIMIT 300');
    foreach ($orders as &$o) {
        $o['check'] = invoice_check($o);
    }
    unset($o);
    render('admin/invoices', ['title' => 'Rapprochement des factures', 'orders' => $orders, 'filter' => $filter,
        'counts' => [
            'todo' => (int)val("SELECT COUNT(*) FROM purchase_orders WHERE invoice_amount IS NULL AND status IN ('partiel','recu')"),
            'ecart' => (int)val("SELECT COUNT(*) FROM purchase_orders WHERE invoice_status = 'ecart'"),
        ]]);
}

// ---------------------------------------------------------------- Exports comptables

function admin_exports(): void
{
    require_admin();
    render('admin/exports', ['title' => 'Exports comptables',
        'centers' => all('SELECT id, name FROM centers ORDER BY name'), 'suppliers' => all('SELECT id, name FROM suppliers ORDER BY name')]);
}

function admin_exports_download(): void
{
    require_admin();
    $from = (string)input('from') ?: date('Y-01-01');
    $to = (string)input('to') ?: date('Y-m-d');
    $type = (string)input('type', 'lines');
    $where = "po.status IN ('commande','partiel','recu') AND po.ordered_at >= ? AND po.ordered_at < ?";
    $params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
    if ($c = input_int('center')) {
        $where .= ' AND po.center_id = ?';
        $params[] = $c;
    }
    if ($s = input_int('supplier')) {
        $where .= ' AND po.supplier_id = ?';
        $params[] = $s;
    }
    $num = fn($v) => number_format((float)$v, 2, ',', '');
    $base = "FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id JOIN centers c ON c.id = po.center_id
             JOIN suppliers s ON s.id = po.supplier_id LEFT JOIN products p ON p.id = l.product_id LEFT JOIN categories cat ON cat.id = p.category_id WHERE $where";
    [$head, $rows] = match ($type) {
        'center', 'supplier', 'category', 'month', 'center_month' => (function () use ($type, $base, $params, $num) {
            $dim = ['center' => 'c.name', 'supplier' => 's.name', 'category' => "COALESCE(cat.name, 'Sans catégorie')", 'month' => 'SUBSTR(po.ordered_at, 1, 7)',
                'center_month' => "c.name || ' | ' || SUBSTR(po.ordered_at, 1, 7)"][$type];
            if (db_driver() === 'mysql' && $type === 'center_month') {
                $dim = "CONCAT(c.name, ' | ', SUBSTR(po.ordered_at, 1, 7))";
            }
            $data = all("SELECT $dim AS k, COUNT(DISTINCT po.id) AS nb, SUM(l.qty*l.unit_price) AS total,
                         SUM(l.qty*(l.catalog_price - l.unit_price)) AS savings, SUM(l.qty*l.unit_price*(1 + COALESCE(p.vat_rate, 20)/100)) AS ttc
                         $base GROUP BY $dim ORDER BY $dim", $params);
            $label = ['center' => 'Centre', 'supplier' => 'Fournisseur', 'category' => 'Catégorie', 'month' => 'Mois', 'center_month' => 'Centre | Mois'][$type];
            return [[$label, 'Nombre de bons', 'Total HT', 'Total TTC estimé', 'Économies négociées'],
                array_map(fn($r) => [$r['k'], $r['nb'], $num($r['total']), $num($r['ttc']), $num(max(0, (float)$r['savings']))], $data)];
        })(),
        'invoices' => (function () use ($where, $params, $num) {
            $data = all("SELECT po.*, c.name AS center_name, s.name AS supplier_name FROM purchase_orders po JOIN centers c ON c.id = po.center_id
                         JOIN suppliers s ON s.id = po.supplier_id WHERE $where ORDER BY po.ordered_at", $params);
            return [['Bon', 'Groupe', 'Date commande', 'Centre', 'Fournisseur', 'Montant commandé HT', 'Montant reçu HT', 'N° facture', 'Date facture', 'Montant facture HT', 'Écart', 'Statut'],
                array_map(function ($po) use ($num) {
                    $ch = invoice_check($po);
                    return [$po['po_number'], $po['group_ref'], date_fr($po['ordered_at']), $po['center_name'], $po['supplier_name'], $num($ch['ordered']), $num($ch['expected']),
                        $po['invoice_number'], $po['invoice_date'] ? date_fr($po['invoice_date']) : '', $po['invoice_amount'] !== null ? $num($po['invoice_amount']) : '',
                        $ch['diff'] !== null ? $num($ch['diff']) : '', ['ok' => 'Conforme', 'ecart' => 'Écart', 'none' => 'À saisir'][$ch['status']]];
                }, $data)];
        })(),
        default => (function () use ($base, $params, $num) {
            $data = all("SELECT po.po_number, po.group_ref, po.ordered_at, po.status, c.name AS center, s.name AS supplier, cat.name AS category,
                         l.reference, l.label, l.unit, l.qty, l.qty_received, l.unit_price, l.catalog_price, COALESCE(p.vat_rate, 20) AS vat,
                         po.invoice_number $base ORDER BY po.ordered_at, po.po_number", $params);
            return [['Date commande', 'Bon', 'Groupe', 'Statut', 'Centre', 'Fournisseur', 'Catégorie', 'Référence', 'Désignation', 'Conditionnement', 'Quantité',
                'Reçu', 'PU HT', 'Total HT', 'TVA %', 'Total TTC', 'Économie vs catalogue', 'N° facture'],
                array_map(fn($r) => [date_fr($r['ordered_at']), $r['po_number'], $r['group_ref'], PO_STATUSES[$r['status']]['label'] ?? $r['status'], $r['center'], $r['supplier'],
                    $r['category'], $r['reference'], $r['label'], $r['unit'], $r['qty'], $r['qty_received'], $num($r['unit_price']), $num($r['qty'] * $r['unit_price']),
                    $num($r['vat']), $num($r['qty'] * $r['unit_price'] * (1 + $r['vat'] / 100)), $num(max(0, ($r['catalog_price'] - $r['unit_price']) * $r['qty'])), $r['invoice_number']], $data)];
        })(),
    };
    audit('Export comptable', null, null, "$type $from → $to");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export-' . $type . '-' . $from . '-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $head, ';', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, $r, ';', '"', '');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------- Journal d'audit

function admin_audit(): void
{
    require_admin();
    $sql = 'SELECT a.*, u.first_name, u.last_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE 1=1';
    $params = [];
    if ($q = trim((string)input('q', ''))) {
        $sql .= ' AND (a.action LIKE ? OR a.details LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($uid = input_int('user')) {
        $sql .= ' AND a.user_id = ?';
        $params[] = $uid;
    }
    render('admin/audit', ['title' => 'Journal d\'audit', 'rows' => all($sql . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 400', $params),
        'q' => $q, 'user' => $uid, 'users' => all("SELECT id, first_name, last_name FROM users WHERE role IN ('admin','manager') ORDER BY last_name")]);
}

// ---------------------------------------------------------------- Sauvegardes quotidiennes & file d'e-mails

function admin_backup_daily(): void
{
    require_admin();
    $action = (string)input('action');
    try {
        if ($action === 'now') {
            $f = cron_daily_backup(true);
            audit('Sauvegarde de la base', null, null, $f);
            flash('success', 'Sauvegarde de la base créée : ' . $f);
        } elseif ($action === 'download') {
            $file = (string)input('file');
            $path = daily_backups_dir() . '/' . $file;
            if (!preg_match('/^base-[\d-]+\.zip$/', $file) || !is_file($path)) {
                abort(404);
            }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            readfile($path);
            exit;
        } elseif ($action === 'restore') {
            if (!admin_check_password()) {
                redirect('admin/updates');
            }
            $file = (string)input('file');
            $path = daily_backups_dir() . '/' . $file;
            if (!preg_match('/^base-[\d-]+\.zip$/', $file) || !is_file($path)) {
                throw new RuntimeException('Sauvegarde introuvable.');
            }
            $safety = daily_backups_dir() . '/base-' . date('Y-m-d') . '-avant-restauration-' . date('His') . '.zip';
            backup_db_only($safety, 'Avant restauration');
            $zip = new ZipArchive();
            $zip->open($path);
            $tmp = tempnam(sys_get_temp_dir(), 'dbrest');
            file_put_contents($tmp, (string)$zip->getFromName('__database.jsonl'));
            $zip->close();
            db_restore_from($tmp);
            @unlink($tmp);
            audit('Base restaurée', null, null, $file);
            flash('success', 'Base de données restaurée depuis ' . $file . '. L\'état précédent a été sauvegardé (' . basename($safety) . ').');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates');
}

function admin_mail_queue(): void
{
    require_admin();
    if (input('action') === 'retry') {
        q('UPDATE mail_queue SET attempts = 0, last_error = NULL WHERE sent_at IS NULL');
        $n = mail_queue_process(30);
        flash('success', plural($n, 'e-mail envoyé', 'e-mails envoyés') . '.');
    } elseif (input('action') === 'run') {
        $r = cron_run(true);
        flash('success', 'Tâches exécutées : ' . implode(' · ', array_map(fn($k, $v) => (CRON_TASKS[$k]['label'] ?? $k) . ' : ' . (is_scalar($v) ? $v : json_encode($v)), array_keys($r), $r)));
    }
    redirect('admin/settings');
}
