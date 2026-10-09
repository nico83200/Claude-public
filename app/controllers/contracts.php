<?php
declare(strict_types=1);

/** Contrats et marchés (service achats : administrateur et acheteur). */
function admin_contracts(): void
{
    require_admin();
    $list = contract_list();
    $filter = (string)input('f', '');
    $counts = array_count_values(array_map(fn($c) => $c['status']['key'], $list));
    render('admin/contracts', [
        'title' => 'Contrats et marchés',
        'contracts' => $filter ? array_values(array_filter($list, fn($c) => $c['status']['key'] === $filter)) : $list,
        'all' => $list,
        'counts' => $counts,
        'filter' => $filter,
        'covered' => count(contract_prices_now()),
        'annual' => array_sum(array_map(fn($c) => in_array($c['status']['key'], ['active', 'renew'], true) ? (float)$c['annual_amount'] : 0, $list)),
    ]);
}

function admin_contract(): void
{
    $me = require_admin();
    $id = input_int('id');
    $c = $id ? one('SELECT * FROM contracts WHERE id = ?', [$id]) : null;
    if ($id && !$c) {
        abort(404);
    }

    if (is_post()) {
        $action = (string)input('action', 'save');
        try {
            switch ($action) {
                case 'save':
                    $data = [
                        'name' => mb_substr(trim((string)input('name')), 0, 150),
                        'reference' => mb_substr(trim((string)input('reference')), 0, 80) ?: null,
                        'buying_group' => mb_substr(trim((string)input('buying_group')), 0, 80) ?: null,
                        'start_date' => (string)input('start_date'),
                        'end_date' => (string)input('end_date'),
                        'notice_days' => max(0, min(365, input_int('notice_days', 90))),
                        'tacit_renewal' => input('tacit_renewal') ? 1 : 0,
                        'annual_amount' => input_money('annual_amount', null),
                        'contact' => mb_substr(trim((string)input('contact')), 0, 190) ?: null,
                        'notes' => trim((string)input('notes')) ?: null,
                        'updated_at' => now(),
                    ];
                    $supplierId = input_int('supplier_id');
                    $errors = [];
                    if ($data['name'] === '') {
                        $errors[] = 'Indiquez le nom du contrat.';
                    }
                    if (!$c && !one('SELECT id FROM suppliers WHERE id = ?', [$supplierId])) {
                        $errors[] = 'Choisissez le fournisseur titulaire.';
                    }
                    foreach (['start_date' => 'début', 'end_date' => 'fin'] as $k => $l) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$k]) || !strtotime($data[$k])) {
                            $errors[] = 'Date de ' . $l . ' invalide.';
                        }
                    }
                    if (!$errors && $data['end_date'] < $data['start_date']) {
                        $errors[] = 'La fin du contrat doit être après son début.';
                    }
                    if ($errors) {
                        throw new RuntimeException(implode(' ', $errors));
                    }
                    // Échéance ou préavis modifiés : les alertes repartent de zéro
                    if ($c && ($c['end_date'] !== $data['end_date'] || (int)$c['notice_days'] !== $data['notice_days'])) {
                        $data += ['alerted_at' => null, 'expired_notified_at' => null];
                    }
                    if ($file = contract_upload('file')) {
                        if ($c && $c['file']) {
                            @unlink(contracts_dir() . '/' . basename($c['file']));
                        }
                        $data['file'] = $file;
                    }
                    if ($c) {
                        update('contracts', $data, 'id = ?', [$c['id']]);
                    } else {
                        $id = insert('contracts', $data + ['supplier_id' => $supplierId, 'created_by' => $me['id'], 'created_at' => now()]);
                    }
                    contracts_apply_prices();
                    audit($c ? 'Contrat modifié' : 'Contrat créé', 'contract', $id, $data['name']);
                    flash('success', $c ? 'Contrat enregistré.' : 'Contrat créé : saisissez maintenant ses prix contractuels.');
                    contract_redirect((int)$id, $c ? '' : 'prices');

                case 'prices':
                    $c || abort(404);
                    $prices = [];
                    foreach ((array)($_POST['price'] ?? []) as $pid => $v) {
                        $v = parse_number((string)$v);
                        if ($v !== null && $v > 0) {
                            $prices[(int)$pid] = round($v, 2);
                        }
                    }
                    [$pasted, $unknown] = contract_parse_prices((string)input('paste', ''), (int)$c['supplier_id']);
                    $prices = $pasted + $prices;
                    $valid = array_map('intval', array_column(all('SELECT id FROM products WHERE supplier_id = ?', [$c['supplier_id']]), 'id'));
                    tx(function () use ($c, $prices, $valid) {
                        q('DELETE FROM contract_prices WHERE contract_id = ?', [$c['id']]);
                        foreach ($prices as $pid => $price) {
                            if (in_array($pid, $valid, true)) {
                                insert('contract_prices', ['contract_id' => $c['id'], 'product_id' => $pid, 'price' => $price]);
                            }
                        }
                    });
                    $applied = contracts_apply_prices();
                    audit('Prix contractuels enregistrés', 'contract', (int)$c['id'], count($prices) . ' article(s)');
                    flash($unknown ? 'info' : 'success', plural(count($prices), 'prix contractuel enregistré', 'prix contractuels enregistrés')
                        . ($applied ? ' · ' . plural($applied, 'tarif d\'article mis à jour', 'tarifs d\'articles mis à jour') : '')
                        . ($unknown ? '. Lignes non reconnues (référence inconnue chez ce fournisseur ou prix illisible) : ' . implode(' · ', array_slice($unknown, 0, 8)) . (count($unknown) > 8 ? '…' : '') : '.'));
                    contract_redirect((int)$c['id'], 'prices');

                case 'take_current':
                    $c || abort(404);
                    $n = 0;
                    foreach (all('SELECT id, catalog_price, negotiated_price FROM products WHERE supplier_id = ? AND active = 1', [$c['supplier_id']]) as $p) {
                        if ($p['negotiated_price'] !== null && (float)$p['negotiated_price'] > 0
                            && !one('SELECT 1 FROM contract_prices WHERE contract_id = ? AND product_id = ?', [$c['id'], $p['id']])) {
                            insert('contract_prices', ['contract_id' => $c['id'], 'product_id' => $p['id'], 'price' => $p['negotiated_price']]);
                            $n++;
                        }
                    }
                    flash('success', $n ? plural($n, 'tarif négocié actuel repris', 'tarifs négociés actuels repris') . ' dans le contrat.' : 'Aucun nouveau tarif négocié à reprendre.');
                    contract_redirect((int)$c['id'], 'prices');

                case 'renew':
                    $c || abort(404);
                    $months = input_int('months', 12);
                    $r = contract_renew((int)$c['id'], $months);
                    audit('Contrat prolongé', 'contract', (int)$c['id'], $c['name'] . ' jusqu\'au ' . $r['end_date']);
                    flash('success', 'Contrat prolongé jusqu\'au ' . date_fr($r['end_date']) . ' : les prix contractuels restent appliqués.');
                    redirect('admin/contract', ['id' => $c['id']]);

                case 'delete':
                    $c || abort(404);
                    if ($c['file']) {
                        @unlink(contracts_dir() . '/' . basename($c['file']));
                    }
                    q('DELETE FROM contract_prices WHERE contract_id = ?', [$c['id']]);
                    q('DELETE FROM contracts WHERE id = ?', [$c['id']]);
                    audit('Contrat supprimé', 'contract', (int)$c['id'], $c['name']);
                    flash('success', 'Contrat supprimé. Les articles gardent leur tarif actuel.');
                    redirect('admin/contracts');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            if (!$c) {
                $_SESSION['contract_draft'] = $_POST;
            }
            redirect('admin/contract', ['id' => $c['id'] ?? null]);
        }
    }

    $draft = $c ? null : ($_SESSION['contract_draft'] ?? null);
    unset($_SESSION['contract_draft']);
    $products = [];
    if ($c) {
        $products = all('SELECT p.id, p.name, p.reference, p.unit, p.catalog_price, p.negotiated_price, p.active, cp.price AS contract_price
                         FROM products p LEFT JOIN contract_prices cp ON cp.product_id = p.id AND cp.contract_id = ?
                         WHERE p.supplier_id = ? AND (p.active = 1 OR cp.price IS NOT NULL)
                         ORDER BY cp.price IS NULL, p.name', [$c['id'], $c['supplier_id']]);
    }
    render('admin/contract', [
        'title' => $c ? $c['name'] : 'Nouveau contrat',
        'c' => $c ?? ($draft ?: ['supplier_id' => input_int('supplier'), 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+3 years -1 day')), 'notice_days' => 90]),
        'isNew' => !$c,
        'supplier' => $c ? one('SELECT id, name, color FROM suppliers WHERE id = ?', [$c['supplier_id']]) : null,
        'suppliers' => all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name'),
        'products' => $products,
        'status' => $c ? contract_status($c) : null,
    ]);
}

function contract_redirect(int $id, string $anchor = ''): never
{
    header('Location: ' . url('admin/contract', ['id' => $id]) . ($anchor ? '#' . $anchor : ''));
    exit;
}

/** Document du contrat (PDF), réservé au service achats. */
function admin_contract_file(): void
{
    require_admin();
    $c = one('SELECT file, name FROM contracts WHERE id = ?', [input_int('id')]);
    $path = $c && $c['file'] ? contracts_dir() . '/' . basename($c['file']) : null;
    if (!$path || !is_file($path)) {
        abort(404);
    }
    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
    header('Content-Disposition: inline; filename="contrat-' . preg_replace('/[^a-z0-9]+/i', '-', $c['name']) . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    readfile($path);
    exit;
}

/** Enregistre le document du contrat (PDF ou image) hors de la zone web. */
function contract_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 20 * 1024 * 1024) {
        throw new RuntimeException('Envoi du document impossible (20 Mo maximum).');
    }
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
    if (!$ext) {
        throw new RuntimeException('Document du contrat : PDF, JPG ou PNG uniquement.');
    }
    $name = 'contrat-' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], contracts_dir() . '/' . $name)) {
        throw new RuntimeException('Impossible d\'enregistrer le document (droits du dossier storage/ ?).');
    }
    return $name;
}
