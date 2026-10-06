<?php
declare(strict_types=1);

// ---------------------------------------------------------------- Fournisseurs

function admin_suppliers(): void
{
    require_admin();
    $suppliers = all("SELECT s.*,
                        (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id AND p.active = 1) AS nb_products,
                        (SELECT COUNT(*) FROM request_lines rl WHERE rl.supplier_id = s.id AND rl.status = 'pending') AS nb_pending,
                        (SELECT COALESCE(SUM(rl.qty*rl.unit_price),0) FROM request_lines rl WHERE rl.supplier_id = s.id AND rl.status = 'pending') AS pending_total
                      FROM suppliers s ORDER BY s.active DESC, s.name");
    $restricted = [];
    foreach (all('SELECT sc.supplier_id, c.name FROM supplier_centers sc JOIN centers c ON c.id = sc.center_id') as $r) {
        $restricted[(int)$r['supplier_id']][] = $r['name'];
    }
    render('admin/suppliers', ['title' => 'Fournisseurs', 'suppliers' => $suppliers, 'restricted' => $restricted]);
}

function admin_supplier_edit(): void
{
    require_admin();
    $id = input_int('id');
    $s = $id ? one('SELECT * FROM suppliers WHERE id = ?', [$id]) : null;
    if ($id && !$s) {
        abort(404);
    }
    $centers = all('SELECT id, name FROM centers ORDER BY name');
    $selected = $id ? array_map('intval', array_column(all('SELECT center_id FROM supplier_centers WHERE supplier_id = ?', [$id]), 'center_id')) : [];

    if (is_post()) {
        $data = [
            'name' => (string)input('name'),
            'contact_name' => (string)input('contact_name') ?: null,
            'email' => (string)input('email') ?: null,
            'phone' => (string)input('phone') ?: null,
            'website' => (string)input('website') ?: null,
            'customer_number' => (string)input('customer_number') ?: null,
            'min_order_amount' => input_money('min_order_amount', 0.0),
            'shipping_fee' => input_money('shipping_fee', 0.0),
            'free_shipping_from' => input_money('free_shipping_from', 0.0),
            'delivery_delay' => (string)input('delivery_delay') ?: null,
            'order_method' => (string)input('order_method') ?: null,
            'notes' => (string)input('notes') ?: null,
            'all_centers' => input('all_centers') === '1' ? 1 : 0,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)input('color')) ? input('color') : '#0ea5e9',
            'active' => input('active') === '1' ? 1 : 0,
        ];
        $centerIds = array_map('intval', (array)($_POST['centers'] ?? []));
        if ($data['name'] === '') {
            flash('error', 'Le nom du fournisseur est obligatoire.');
        } elseif (!$data['all_centers'] && !$centerIds) {
            flash('error', 'Sélectionnez au moins un centre, ou rendez le fournisseur disponible pour tous les centres.');
        } else {
            tx(function () use (&$id, $data, $centerIds) {
                if ($id) {
                    update('suppliers', $data, 'id = ?', [$id]);
                } else {
                    $id = insert('suppliers', $data + ['created_at' => now()]);
                }
                q('DELETE FROM supplier_centers WHERE supplier_id = ?', [$id]);
                if (!$data['all_centers']) {
                    foreach (array_unique($centerIds) as $cid) {
                        insert('supplier_centers', ['supplier_id' => $id, 'center_id' => $cid]);
                    }
                }
            });
            flash('success', 'Fournisseur enregistré.');
            redirect('admin/suppliers');
        }
        $s = array_merge($s ?? [], $data);
        $selected = $centerIds;
    }
    render('admin/supplier_form', ['title' => $s ? 'Fournisseur : ' . $s['name'] : 'Nouveau fournisseur', 's' => $s, 'centers' => $centers, 'selected' => $selected]);
}

// ---------------------------------------------------------------- Articles

function admin_products(): void
{
    require_admin();
    $q = (string)input('q', '');
    $sup = input_int('sup');
    $cat = input_int('cat');
    $state = (string)input('state', 'active');
    $sql = 'SELECT p.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM products p JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1';
    $params = [];
    if ($sup) {
        $sql .= ' AND p.supplier_id = ?';
        $params[] = $sup;
    }
    if ($cat) {
        $sql .= ' AND p.category_id = ?';
        $params[] = $cat;
    }
    if ($state === 'active') {
        $sql .= ' AND p.active = 1';
    } elseif ($state === 'inactive') {
        $sql .= ' AND p.active = 0';
    } elseif ($state === 'nophoto') {
        $sql .= " AND (p.image IS NULL OR p.image = '')";
    }
    $sql .= ' ORDER BY p.name';
    $products = all($sql, $params);
    if ($q !== '') {
        $scores = search_local($products, $q);
        $byId = array_column($products, null, 'id');
        $products = array_map(fn($id) => $byId[$id], array_keys($scores));
    }
    render('admin/products', [
        'title' => 'Articles', 'products' => $products, 'q' => $q, 'sup' => $sup, 'cat' => $cat, 'state' => $state,
        'suppliers' => all('SELECT id, name FROM suppliers ORDER BY name'),
        'categories' => all('SELECT id, name FROM categories ORDER BY position, name'),
    ]);
}

function admin_product_edit(): void
{
    require_admin();
    $id = input_int('id');
    $p = $id ? one('SELECT * FROM products WHERE id = ?', [$id]) : null;
    if ($id && !$p) {
        abort(404);
    }
    if (!$p && input_int('copy')) {
        $p = one('SELECT * FROM products WHERE id = ?', [input_int('copy')]);
        if ($p) {
            $p['id'] = null;
            $p['image'] = null;
            $p['name'] .= ' (copie)';
        }
    }
    if (is_post()) {
        $data = [
            'supplier_id' => input_int('supplier_id'),
            'category_id' => input_int('category_id') ?: null,
            'reference' => (string)input('reference') ?: null,
            'name' => (string)input('name'),
            'description' => (string)input('description') ?: null,
            'unit' => (string)input('unit') ?: null,
            'catalog_price' => input_money('catalog_price', 0.0),
            'negotiated_price' => input_money('negotiated_price', null),
            'vat_rate' => input_money('vat_rate', 20.0),
            'keywords' => (string)input('keywords') ?: null,
            'min_qty' => max(1, input_int('min_qty', 1)),
            'active' => input('active') === '1' ? 1 : 0,
            'updated_at' => now(),
        ];
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'La désignation est obligatoire.';
        }
        if (!val('SELECT COUNT(*) FROM suppliers WHERE id = ?', [$data['supplier_id']])) {
            $errors[] = 'Choisissez un fournisseur.';
        }
        try {
            $img = handle_image_upload('image');
            if ($img) {
                delete_image($p['image'] ?? null);
                $data['image'] = $img;
            } elseif (input('remove_image') === '1') {
                delete_image($p['image'] ?? null);
                $data['image'] = null;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
        if (!$errors) {
            if ($id) {
                update('products', $data, 'id = ?', [$id]);
            } else {
                $id = insert('products', $data + ['created_at' => now()]);
            }
            flash('success', 'Article « ' . $data['name'] . ' » enregistré.');
            if (input('then') === 'new') {
                redirect('admin/product', ['supplier_id' => $data['supplier_id']]);
            }
            redirect('admin/products', ['sup' => $data['supplier_id']]);
        }
        foreach ($errors as $err) {
            flash('error', $err);
        }
        $p = array_merge($p ?? [], $data);
    }
    render('admin/product_form', [
        'title' => $id ? 'Modifier l\'article' : 'Nouvel article', 'p' => $p,
        'suppliers' => all('SELECT id, name FROM suppliers ORDER BY active DESC, name'),
        'categories' => all('SELECT id, name FROM categories ORDER BY position, name'),
        'defaultSupplier' => input_int('supplier_id'),
    ]);
}

function admin_product_toggle(): void
{
    require_admin();
    $p = one('SELECT * FROM products WHERE id = ?', [input_int('id')]);
    if ($p) {
        update('products', ['active' => $p['active'] ? 0 : 1], 'id = ?', [$p['id']]);
        flash('success', $p['active'] ? 'Article masqué du catalogue.' : 'Article réactivé.');
    }
    redirect_back('admin/products');
}

const IMPORT_COLUMNS = ['fournisseur', 'reference', 'designation', 'description', 'categorie', 'conditionnement', 'prix_catalogue', 'prix_negocie', 'mots_cles', 'tva'];

/** Import en masse d'un catalogue fournisseur (CSV séparateur « ; »). */
function admin_products_import(): void
{
    require_admin();
    $report = null;
    if (is_post()) {
        if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            flash('error', 'Choisissez un fichier CSV.');
            redirect('admin/products/import');
        }
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $first = fgets($fh) ?: '';
        $sep = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        rewind($fh);
        $header = fgetcsv($fh, 0, $sep, '"', '');
        $header = array_map(fn($h) => str_replace(' ', '_', search_normalize(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h))), $header ?: []);
        $report = ['created' => 0, 'updated' => 0, 'errors' => []];
        $suppliers = array_change_key_case(array_column(all('SELECT id, name FROM suppliers'), 'id', 'name'), CASE_LOWER);
        $categories = array_change_key_case(array_column(all('SELECT id, name FROM categories'), 'id', 'name'), CASE_LOWER);
        $defaultSupplier = input_int('supplier_id');
        $line = 1;
        tx(function () use ($fh, $sep, $header, &$report, &$suppliers, &$categories, $defaultSupplier, &$line) {
            while (($row = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
                $line++;
                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }
                $r = [];
                foreach ($header as $i => $h) {
                    $r[$h] = trim((string)($row[$i] ?? ''));
                }
                $name = $r['designation'] ?? $r['nom'] ?? '';
                if ($name === '') {
                    $report['errors'][] = "Ligne $line : désignation manquante.";
                    continue;
                }
                $supId = $defaultSupplier;
                if (!empty($r['fournisseur'])) {
                    $key = mb_strtolower($r['fournisseur']);
                    if (!isset($suppliers[$key])) {
                        $suppliers[$key] = insert('suppliers', ['name' => $r['fournisseur'], 'all_centers' => 1, 'active' => 1, 'created_at' => now()]);
                    }
                    $supId = (int)$suppliers[$key];
                }
                if (!$supId) {
                    $report['errors'][] = "Ligne $line : fournisseur manquant.";
                    continue;
                }
                $catId = null;
                if (!empty($r['categorie'])) {
                    $key = mb_strtolower($r['categorie']);
                    if (!isset($categories[$key])) {
                        $categories[$key] = insert('categories', ['name' => $r['categorie'], 'icon' => 'box', 'color' => palette()[count($categories) % 12], 'position' => 99]);
                    }
                    $catId = (int)$categories[$key];
                }
                $num = function (?string $v): ?float {
                    if ($v === null || $v === '') {
                        return null;
                    }
                    $v = str_replace([' ', "\u{00A0}", '€'], '', $v);
                    $v = str_replace(',', '.', $v);
                    return is_numeric($v) ? round((float)$v, 2) : null;
                };
                $data = [
                    'supplier_id' => $supId, 'category_id' => $catId,
                    'reference' => ($r['reference'] ?? '') ?: null, 'name' => $name,
                    'description' => ($r['description'] ?? '') ?: null,
                    'unit' => ($r['conditionnement'] ?? '') ?: null,
                    'catalog_price' => $num($r['prix_catalogue'] ?? null) ?? 0,
                    'negotiated_price' => $num($r['prix_negocie'] ?? null),
                    'keywords' => ($r['mots_cles'] ?? '') ?: null,
                    'vat_rate' => $num($r['tva'] ?? null) ?? 20,
                    'updated_at' => now(),
                ];
                $existing = $data['reference'] ? val('SELECT id FROM products WHERE supplier_id = ? AND reference = ?', [$supId, $data['reference']]) : null;
                if ($existing) {
                    update('products', $data, 'id = ?', [$existing]);
                    $report['updated']++;
                } else {
                    insert('products', $data + ['active' => 1, 'created_at' => now()]);
                    $report['created']++;
                }
            }
        });
        fclose($fh);
    }
    render('admin/products_import', [
        'title' => 'Importer des articles', 'report' => $report,
        'suppliers' => all('SELECT id, name FROM suppliers ORDER BY name'),
    ]);
}

function admin_products_export(): void
{
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalogue-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, IMPORT_COLUMNS, ';', '"', '');
    $rows = all('SELECT p.*, s.name AS supplier_name, c.name AS category_name FROM products p
                 JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.active = 1 ORDER BY s.name, p.name');
    foreach ($rows as $p) {
        fputcsv($out, [
            $p['supplier_name'], $p['reference'], $p['name'], $p['description'], $p['category_name'], $p['unit'],
            number_format((float)$p['catalog_price'], 2, ',', ''),
            $p['negotiated_price'] !== null ? number_format((float)$p['negotiated_price'], 2, ',', '') : '',
            $p['keywords'], number_format((float)$p['vat_rate'], 2, ',', ''),
        ], ';', '"', '');
    }
    fclose($out);
    exit;
}

// ---------------------------------------------------------------- Catégories

function admin_categories(): void
{
    require_admin();
    if (is_post()) {
        $action = input('action');
        if ($action === 'delete') {
            q('UPDATE products SET category_id = NULL WHERE category_id = ?', [input_int('id')]);
            q('DELETE FROM categories WHERE id = ?', [input_int('id')]);
            flash('success', 'Catégorie supprimée (les articles sont conservés sans catégorie).');
        } else {
            $data = [
                'name' => (string)input('name'),
                'icon' => in_array(input('icon'), icon_choices(), true) ? input('icon') : 'box',
                'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)input('color')) ? input('color') : '#8b5cf6',
                'position' => input_int('position'),
            ];
            if ($data['name'] === '') {
                flash('error', 'Nom obligatoire.');
            } elseif ($id = input_int('id')) {
                update('categories', $data, 'id = ?', [$id]);
                flash('success', 'Catégorie mise à jour.');
            } else {
                insert('categories', $data);
                flash('success', 'Catégorie créée.');
            }
        }
        redirect('admin/categories');
    }
    render('admin/categories', [
        'title' => 'Catégories',
        'categories' => all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS nb FROM categories c ORDER BY c.position, c.name'),
    ]);
}
