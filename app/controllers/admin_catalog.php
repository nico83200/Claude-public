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
            'order_method' => isset(ORDER_METHODS[(string)input('order_method')]) ? (string)input('order_method') : 'other',
            'order_url' => (string)input('order_url') ?: null,
            'order_note' => mb_substr((string)input('order_note'), 0, 255) ?: null,
            'notes' => (string)input('notes') ?: null,
            'all_centers' => input('all_centers') === '1' ? 1 : 0,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)input('color')) ? input('color') : '#0ea5e9',
            'active' => input('active') === '1' ? 1 : 0,
        ];
        $centerIds = array_map('intval', (array)($_POST['centers'] ?? []));
        foreach (['website', 'order_url'] as $k) {
            if ($data[$k] && !preg_match('#^https?://#i', $data[$k])) {
                $data[$k] = 'https://' . $data[$k];
            }
        }
        if ($data['name'] === '') {
            flash('error', 'Le nom du fournisseur est obligatoire.');
        } elseif ($data['order_method'] === 'online' && !$data['order_url'] && !$data['website']) {
            flash('error', 'Commande en ligne : indiquez l\'adresse du site de commande.');
        } elseif ($data['order_method'] === 'email' && !$data['email']) {
            flash('error', 'Envoi du PDF par e-mail : indiquez l\'e-mail de commande du fournisseur.');
        } elseif (!$data['all_centers'] && !$centerIds) {
            flash('error', 'Sélectionnez au moins un centre, ou rendez le fournisseur disponible pour tous les centres.');
        } else {
            tx(function () use (&$id, $data, $centerIds) {
                if ($id) {
                    $before = one('SELECT * FROM suppliers WHERE id = ?', [$id]);
                    if ($diff = audit_diff($before, $data, ['min_order_amount' => 'Minimum', 'shipping_fee' => 'Frais de port', 'free_shipping_from' => 'Franco',
                        'email' => 'E-mail', 'order_method' => 'Mode de commande', 'order_url' => 'Site de commande', 'all_centers' => 'Tous centres', 'active' => 'Actif'])) {
                        audit('Fournisseur modifié', 'supplier', $id, $data['name'] . ' — ' . $diff);
                    }
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

/** Suppression d'un ou plusieurs articles (masqués seulement s'ils ont un historique de commandes). */
function admin_products_delete(): void
{
    require_admin();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    if (!$ids) {
        flash('error', 'Aucun article sélectionné.');
        redirect('admin/products');
    }
    $deleted = $archived = 0;
    foreach ($ids as $id) {
        $name = (string)val('SELECT name FROM products WHERE id = ?', [$id]);
        $r = product_delete($id);
        if ($r === 'deleted' || $r === 'archived') {
            $r === 'deleted' ? $deleted++ : $archived++;
            audit($r === 'deleted' ? 'Article supprimé' : 'Article masqué (historique conservé)', 'product', $id, $name);
        }
    }
    q('DELETE FROM ai_cache');
    $msg = [];
    if ($deleted) {
        $msg[] = plural($deleted, 'article supprimé', 'articles supprimés');
    }
    if ($archived) {
        $msg[] = plural($archived, 'article figurait', 'articles figuraient') . ' dans des commandes : '
            . ($archived > 1 ? 'ils ont été masqués' : 'il a été masqué') . ' du catalogue pour conserver l\'historique (filtre « Inactifs »)';
    }
    flash('success', ucfirst(implode('. ', $msg)) . '.');
    redirect('admin/products', ['state' => (string)input('state', 'active')]);
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
            'barcode' => preg_replace('/\s+/', '', (string)input('barcode')) ?: null,
            'compare_group' => mb_substr(trim((string)input('compare_group')), 0, 80) ?: null,
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
        if ($data['barcode'] && val('SELECT COUNT(*) FROM products WHERE barcode = ? AND id <> ?', [$data['barcode'], $id])) {
            $errors[] = 'Ce code-barres est déjà attribué à un autre article.';
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
                $before = one('SELECT * FROM products WHERE id = ?', [$id]);
                update('products', $data, 'id = ?', [$id]);
                if ($diff = audit_diff($before, $data, ['name' => 'Désignation', 'supplier_id' => 'Fournisseur', 'catalog_price' => 'Tarif catalogue',
                    'negotiated_price' => 'Tarif négocié', 'barcode' => 'Code-barres', 'active' => 'Actif', 'compare_group' => 'Groupe d\'équivalence'])) {
                    audit('Article modifié', 'product', $id, $data['name'] . ' — ' . $diff);
                }
            } else {
                $id = insert('products', $data + ['created_at' => now()]);
                audit('Article créé', 'product', $id, $data['name']);
            }
            price_record($id, (float)$data['catalog_price'], $data['negotiated_price'] !== null ? (float)$data['negotiated_price'] : null, 'Fiche article');
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
        'usedInOrders' => !empty($p['id']) ? (int)val('SELECT COUNT(*) FROM request_lines WHERE product_id = ?', [$p['id']]) + (int)val('SELECT COUNT(*) FROM purchase_order_lines WHERE product_id = ?', [$p['id']]) : 0,
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


/** Import en masse d'un catalogue fournisseur (CSV séparateur « ; »). */
/**
 * Import assisté : 1. fichier → 2. correspondance des colonnes (IA) → 3. fournisseurs, catégories et aperçu → import.
 */
function admin_products_import(): void
{
    $me = require_admin();
    $step = (string)input('step', '');
    $state = ($t = (string)input('token', '')) !== '' ? import_load($t) : null;
    if ($t !== '' && !$state) {
        flash('error', 'Import expiré ou introuvable : envoyez à nouveau le fichier.');
        redirect('admin/products/import');
    }

    if (is_post() && $step === 'upload') {
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            flash('error', 'Choisissez un fichier (CSV, Excel .xlsx ou OpenDocument .ods).');
            redirect('admin/products/import');
        }
        try {
            $state = import_start($f['tmp_name'], (string)$f['name'], (int)$me['id']);
        } catch (Throwable $e) {
            flash('error', 'Lecture impossible : ' . $e->getMessage());
            redirect('admin/products/import');
        }
        $state['default_supplier'] = input_int('supplier_id');
        $state['mapping'] = import_guess_mapping($state['headers']);
        if (input('use_ai') === '1') {
            @set_time_limit(180);
            $ai = import_ai_mapping($state['headers'], $state['rows']);
            if ($ai) {
                // L'IA décide ; les règles locales complètent les champs qu'elle a laissés vides (sans réutiliser une colonne)
                $merged = $ai['mapping'];
                $usedCols = array_flip($merged);
                foreach ($state['mapping'] as $field => $col) {
                    if (!isset($merged[$field]) && !isset($usedCols[$col])) {
                        $merged[$field] = $col;
                        $usedCols[$col] = true;
                    }
                }
                $state['mapping'] = $merged;
                [$state['mapping_source'], $state['prices_ttc'], $state['mapping_notes']] = ['ai', $ai['prices_ttc'], $ai['notes']];
            } else {
                $state['ai_error'] = ai_last_error();
            }
        }
        $state['use_ai'] = input('use_ai') === '1';
        import_save($state);
        redirect('admin/products/import', ['token' => $state['token'], 'step' => 'map']);
    }

    if (is_post() && $step === 'map' && $state) {
        $map = [];
        foreach ((array)($_POST['map'] ?? []) as $field => $col) {
            if (isset(IMPORT_FIELDS[$field]) && $col !== '' && isset($state['headers'][(int)$col])) {
                $map[$field] = (int)$col;
            }
        }
        if (!isset($map['name'])) {
            flash('error', 'Indiquez au moins la colonne qui contient la désignation des articles.');
            redirect('admin/products/import', ['token' => $state['token'], 'step' => 'map']);
        }
        $state['mapping'] = $map;
        $state['prices_ttc'] = input('prices_ttc') === '1';
        $state['default_supplier'] = input_int('supplier_id');
        $state['row_overrides'] = [];
        import_match_values($state, !empty($state['use_ai']));
        import_save($state);
        redirect('admin/products/import', ['token' => $state['token'], 'step' => 'preview']);
    }

    if (is_post() && $step === 'preview' && $state) {
        if (is_numeric(input('price_alert_pct'))) {
            set_setting('price_alert_pct', (string)max(0, min(100, (float)input('price_alert_pct'))));
        }
        // Corrections de l'acheteur : fournisseurs, catégories (valeurs du fichier puis article par article)
        foreach ((array)($_POST['sup'] ?? []) as $k => $id) {
            $v = $state['supplier_values'][(int)$k] ?? null;
            if ($v !== null) {
                $state['supplier_map'][$v] = (int)$id;
            }
        }
        foreach ((array)($_POST['cat'] ?? []) as $k => $id) {
            $v = $state['category_values'][(int)$k] ?? null;
            if ($v !== null) {
                $state['category_map'][$v] = (int)$id;
            }
        }
        $state['row_overrides'] = [];
        foreach ((array)($_POST['rowcat'] ?? []) as $line => $id) {
            if ((int)$id > 0) {
                $state['row_overrides'][(int)$line] = (int)$id;
            }
        }
        $state['default_supplier'] = input_int('supplier_id', (int)$state['default_supplier']);
        $state['create_categories'] = input('create_categories') === '1';
        import_save($state);
        if (input('do') !== 'import') {
            redirect('admin/products/import', ['token' => $state['token'], 'step' => 'preview']);
        }
        $selected = array_flip(array_map('intval', (array)($_POST['rows'] ?? [])));
        $report = import_apply($state, $selected, input('update_existing') === '1');
        $inc = $report['increases'];
        @unlink(import_dir() . '/' . $state['token'] . '.json');
        audit('Import d\'articles', 'product', null, $state['file'] . ' : ' . $report['created'] . ' créés, ' . $report['updated'] . ' mis à jour');
        flash('success', sprintf('Import terminé : %s, %s%s%s%s.',
            plural($report['created'], 'article créé', 'articles créés'), plural($report['updated'], 'article mis à jour', 'articles mis à jour'),
            $report['skipped'] ? ', ' . plural($report['skipped'], 'ligne ignorée', 'lignes ignorées') : '',
            $report['suppliers'] ? ', ' . plural($report['suppliers'], 'fournisseur créé', 'fournisseurs créés') : '',
            $report['categories'] ? ', ' . plural($report['categories'], 'catégorie créée', 'catégories créées') : ''));
        if ($inc) {
            flash('error', plural(count($inc), 'hausse de prix', 'hausses de prix') . ' de plus de ' . price_alert_pct() . ' % : '
                . implode(', ', array_map(fn($i) => $i['name'] . ' (+' . $i['pct'] . ' %)', array_slice($inc, 0, 6))) . (count($inc) > 6 ? '…' : '') . '. Pensez à comparer avec les autres fournisseurs (Comparateur).');
        }
        redirect('admin/products');
    }

    $suppliers = all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name');
    $categories = all('SELECT id, name, color FROM categories ORDER BY position, name');
    if ($state && $step === 'map') {
        $samples = [];
        foreach ($state['headers'] as $i => $h) {
            $samples[$i] = array_values(array_filter(array_map(fn($r) => (string)($r[$i] ?? ''), array_slice($state['rows'], 0, 30)), fn($v) => $v !== ''));
        }
        render('admin/products_import_map', ['title' => 'Importer des articles — colonnes', 'state' => $state, 'samples' => $samples, 'suppliers' => $suppliers]);
        return;
    }
    if ($state && $step === 'preview') {
        $state['supplier_values'] = array_keys($state['supplier_map']);
        $state['category_values'] = array_keys($state['category_map']);
        import_save($state);
        $rows = import_prepare($state);
        $counts = array_count_values(array_column($rows, 'status')) + ['new' => 0, 'update' => 0, 'error' => 0];
        render('admin/products_import_preview', ['title' => 'Importer des articles — aperçu', 'state' => $state, 'rows' => $rows,
            'counts' => $counts, 'suppliers' => $suppliers, 'categories' => $categories]);
        return;
    }
    render('admin/products_import', ['title' => 'Importer des articles', 'suppliers' => $suppliers, 'aiReady' => ai_available()]);
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
            $p['keywords'], number_format((float)$p['vat_rate'], 2, ',', ''), $p['barcode'], $p['compare_group'],
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
