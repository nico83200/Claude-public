<?php
declare(strict_types=1);

/**
 * Articles hors catalogue : proposés par les salariés (panier ou code-barres inconnu),
 * complétés puis ajoutés au catalogue — ou refusés — par le service achats.
 */

// ---------------------------------------------------------------- Côté salarié

function suggest_form(): void
{
    $u = require_login();
    $center = require_center();
    $from = input('from') === 'scan' ? 'scan' : 'cart';
    $old = [
        'barcode' => preg_replace('/\s+/', '', (string)input('barcode', '')), 'name' => (string)input('name', ''),
        'brand' => '', 'reference' => '', 'description' => '', 'unit' => '', 'supplier_hint' => '', 'url' => '',
        'estimated_price' => '', 'qty' => $from === 'cart' ? 1 : 0,
    ];
    if (is_post()) {
        foreach (['barcode', 'name', 'brand', 'reference', 'description', 'unit', 'supplier_hint', 'url', 'estimated_price'] as $k) {
            $old[$k] = trim((string)input($k, ''));
        }
        $old['barcode'] = preg_replace('/\s+/', '', $old['barcode']);
        $wantIt = input('add_to_cart') === '1';
        $old['qty'] = $wantIt ? max(1, input_int('qty', 1)) : 0;
        $errors = [];
        if (mb_strlen($old['name']) < 3) {
            $errors[] = 'Indiquez au moins le nom de l\'article.';
        }
        if ($old['url'] !== '' && !filter_var($old['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Le lien web n\'est pas valide (il doit commencer par https://).';
        }
        // Le code-barres correspond peut-être à un article déjà au catalogue
        if ($old['barcode'] !== '' && ($p = product_visible_for_barcode($old['barcode'], (int)$center['id']))) {
            flash('info', 'Ce code-barres correspond déjà à « ' . $p['name'] . ' ».');
            redirect('product', ['id' => $p['id']]);
        }
        try {
            $image = handle_image_upload('photo');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
            $image = null;
        }
        if (!$errors) {
            $price = $old['estimated_price'] !== '' ? (float)str_replace([',', ' ', '€'], ['.', '', ''], $old['estimated_price']) : null;
            $id = insert('product_suggestions', [
                'center_id' => $center['id'], 'user_id' => $u['id'], 'source' => input('source') === 'scan' ? 'scan' : 'cart',
                'barcode' => $old['barcode'] ?: null, 'name' => mb_substr($old['name'], 0, 200),
                'brand' => mb_substr($old['brand'], 0, 120) ?: null, 'reference' => mb_substr($old['reference'], 0, 80) ?: null,
                'description' => $old['description'] ?: null, 'unit' => mb_substr($old['unit'], 0, 80) ?: null,
                'supplier_hint' => mb_substr($old['supplier_hint'], 0, 200) ?: null, 'url' => mb_substr($old['url'], 0, 500) ?: null,
                'estimated_price' => $price !== null && $price > 0 ? round($price, 2) : null,
                'qty' => $old['qty'], 'image' => $image, 'in_cart' => $wantIt ? 1 : 0, 'status' => 'pending', 'created_at' => now(),
            ]);
            if ($wantIt) {
                flash('success', '« ' . $old['name'] . ' » est dans votre panier comme article hors catalogue. Il sera examiné par le service achats à l\'envoi de la demande.');
                redirect('cart');
            }
            notify(admin_ids(), 'suggestion_new', 'Article proposé : ' . $old['name'] . ' (' . $center['name'] . ')',
                $u['first_name'] . ' ' . $u['last_name'] . ' propose d\'ajouter cet article au catalogue'
                    . ($old['barcode'] ? ' (code-barres ' . $old['barcode'] . ')' : '') . '.',
                url('admin/suggestion', ['id' => $id]));
            flash('success', 'Merci ! Votre proposition a été transmise au service achats.');
            redirect('requests', ['scope' => 'suggestions']);
        }
        foreach ($errors as $err) {
            flash('error', $err);
        }
    }
    render('user/suggest', ['title' => 'Proposer un article', 'center' => $center, 'old' => $old, 'from' => $from]);
}

function product_visible_for_barcode(string $code, int $centerId): ?array
{
    return one('SELECT p.* FROM products p JOIN suppliers s ON s.id = p.supplier_id
                WHERE p.barcode = ? AND p.active = 1 AND ' . supplier_visible_sql('s'), [$code, $centerId]);
}

function suggest_update_cart(): void
{
    $u = require_login();
    $s = one('SELECT * FROM product_suggestions WHERE id = ? AND user_id = ? AND in_cart = 1', [input_int('id'), $u['id']]);
    if ($s) {
        if (input('remove') === '1' || input_int('qty') <= 0) {
            q('DELETE FROM product_suggestions WHERE id = ?', [$s['id']]);
            delete_image($s['image']);
        } else {
            update('product_suggestions', ['qty' => min(9999, input_int('qty'))], 'id = ?', [$s['id']]);
        }
    }
    redirect('cart');
}

// ---------------------------------------------------------------- Côté administrateur

function admin_suggestions(): void
{
    require_admin();
    $status = (string)input('status', 'pending');
    $sql = 'SELECT ps.*, u.first_name, u.last_name, u.job, c.name AS center_name, c.color AS center_color, p.name AS product_name
            FROM product_suggestions ps JOIN users u ON u.id = ps.user_id JOIN centers c ON c.id = ps.center_id
            LEFT JOIN products p ON p.id = ps.product_id WHERE ps.in_cart = 0';
    $params = [];
    if (isset(SUGGESTION_STATUSES[$status])) {
        $sql .= ' AND ps.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY ps.created_at DESC LIMIT 200';
    render('admin/suggestions', [
        'title' => 'Articles proposés', 'items' => all($sql, $params), 'status' => $status,
        'counts' => array_column(all('SELECT status, COUNT(*) n FROM product_suggestions WHERE in_cart = 0 GROUP BY status'), 'n', 'status'),
    ]);
}

function admin_suggestion_or_fail(int $id): array
{
    $s = one('SELECT ps.*, u.first_name, u.last_name, u.job, u.email, c.name AS center_name, c.color AS center_color, r.urgent, r.comment AS request_comment
              FROM product_suggestions ps JOIN users u ON u.id = ps.user_id JOIN centers c ON c.id = ps.center_id
              LEFT JOIN requests r ON r.id = ps.request_id WHERE ps.id = ?', [$id]);
    if (!$s || (int)$s['in_cart'] === 1) {
        abort(404, 'Proposition introuvable.');
    }
    return $s;
}

function admin_suggestion(): void
{
    require_admin();
    $s = admin_suggestion_or_fail(input_int('id'));
    // Articles du catalogue qui ressemblent à la proposition (évite les doublons)
    $all = all('SELECT p.*, sp.name AS supplier_name, sp.color AS supplier_color, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
                FROM products p JOIN suppliers sp ON sp.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id WHERE p.active = 1');
    $scores = search_local($all, trim($s['name'] . ' ' . ($s['brand'] ?? '')));
    $byId = array_column($all, null, 'id');
    $similar = array_map(fn($id) => $byId[$id], array_slice(array_keys($scores), 0, 5));
    // Fournisseur pressenti : correspondance avec la base existante
    $suppliers = all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name');
    $guess = 0;
    if ($s['supplier_hint']) {
        foreach ($suppliers as $sp) {
            if (str_contains(search_normalize($s['supplier_hint']), search_normalize($sp['name'])) || str_contains(search_normalize($sp['name']), search_normalize($s['supplier_hint']))) {
                $guess = (int)$sp['id'];
                break;
            }
        }
    }
    render('admin/suggestion', [
        'title' => 'Proposition : ' . $s['name'], 's' => $s, 'similar' => $similar, 'suppliers' => $suppliers, 'guess' => $guess,
        'categories' => all('SELECT id, name FROM categories ORDER BY position, name'),
        'allProducts' => all('SELECT id, name, reference FROM products WHERE active = 1 ORDER BY name'),
    ]);
}

/** Rattache la demande éventuelle (quantité souhaitée) à l'article retenu. */
function suggestion_to_request_line(array $s, array $product): bool
{
    if (!$s['request_id'] || (int)$s['qty'] <= 0) {
        return false;
    }
    insert('request_lines', [
        'request_id' => $s['request_id'], 'center_id' => $s['center_id'], 'product_id' => $product['id'],
        'supplier_id' => $product['supplier_id'], 'qty' => (int)$s['qty'], 'unit_price' => effective_price($product),
        'comment' => 'Article hors catalogue proposé', 'status' => 'pending', 'created_at' => now(),
    ]);
    return true;
}

/** Ajoute l'article au catalogue à partir de la proposition complétée. */
function admin_suggestion_add(): void
{
    require_admin();
    $s = admin_suggestion_or_fail(input_int('id'));
    if ($s['status'] !== 'pending') {
        flash('error', 'Cette proposition a déjà été traitée.');
        redirect('admin/suggestion', ['id' => $s['id']]);
    }
    $supplierId = input_int('supplier_id');
    $newSupplier = trim((string)input('new_supplier', ''));
    $name = trim((string)input('name', ''));
    $barcode = preg_replace('/\s+/', '', (string)input('barcode', '')) ?: null;
    if ($name === '' || (!$supplierId && $newSupplier === '')) {
        flash('error', 'La désignation et le fournisseur sont obligatoires.');
        redirect('admin/suggestion', ['id' => $s['id']]);
    }
    if ($barcode && val('SELECT COUNT(*) FROM products WHERE barcode = ?', [$barcode])) {
        flash('error', 'Ce code-barres est déjà attribué à un article : utilisez « Rattacher à un article existant ».');
        redirect('admin/suggestion', ['id' => $s['id']]);
    }
    try {
        $newImage = handle_image_upload('image');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/suggestion', ['id' => $s['id']]);
    }
    $productId = 0;
    $lineAdded = false;
    tx(function () use ($s, &$supplierId, $newSupplier, $name, $barcode, $newImage, &$productId, &$lineAdded) {
        if (!$supplierId) {
            $supplierId = insert('suppliers', ['name' => $newSupplier, 'all_centers' => 1, 'active' => 1, 'created_at' => now()]);
        }
        $productId = insert('products', [
            'supplier_id' => $supplierId, 'category_id' => input_int('category_id') ?: null,
            'reference' => (string)input('reference') ?: null, 'barcode' => $barcode, 'name' => $name,
            'description' => (string)input('description') ?: null, 'unit' => (string)input('unit') ?: null,
            'catalog_price' => input_money('catalog_price', 0.0), 'negotiated_price' => input_money('negotiated_price', null),
            'vat_rate' => input_money('vat_rate', 20.0), 'keywords' => (string)input('keywords') ?: null,
            'image' => $newImage ?: $s['image'], 'min_qty' => 1, 'active' => 1, 'created_at' => now(),
        ]);
        $product = one('SELECT * FROM products WHERE id = ?', [$productId]);
        $lineAdded = suggestion_to_request_line($s, $product);
        update('product_suggestions', [
            'status' => 'added', 'product_id' => $productId, 'admin_note' => mb_substr((string)input('admin_note', ''), 0, 255) ?: null,
            'handled_by' => user()['id'], 'handled_at' => now(),
        ], 'id = ?', [$s['id']]);
    });
    $np = one('SELECT * FROM products WHERE id = ?', [$productId]);
    price_record($productId, (float)$np['catalog_price'], $np['negotiated_price'] !== null ? (float)$np['negotiated_price'] : null, 'Proposition');
    audit('Proposition ajoutée au catalogue', 'product', $productId, $name);
    notify([(int)$s['user_id']], 'suggestion_done', 'Article ajouté au catalogue : ' . $name,
        'Merci pour votre proposition ! L\'article est désormais disponible dans le catalogue'
            . ($lineAdded ? ' et votre demande de ' . (int)$s['qty'] . ' unité(s) est en cours de traitement.' : '.'),
        url('product', ['id' => $productId]));
    flash('success', 'Article ajouté au catalogue' . ($lineAdded ? ' et demande de ' . (int)$s['qty'] . ' unité(s) placée dans « Demandes à traiter »' : '') . '.');
    redirect('admin/suggestions');
}

/** La proposition correspond à un article déjà au catalogue. */
function admin_suggestion_link(): void
{
    require_admin();
    $s = admin_suggestion_or_fail(input_int('id'));
    $p = one('SELECT * FROM products WHERE id = ?', [input_int('product_id')]);
    if ($s['status'] !== 'pending' || !$p) {
        flash('error', 'Choisissez un article du catalogue.');
        redirect('admin/suggestion', ['id' => $s['id']]);
    }
    $lineAdded = false;
    tx(function () use ($s, $p, &$lineAdded) {
        // Le code-barres scanné complète la fiche existante s'il manquait
        if ($s['barcode'] && !$p['barcode'] && !val('SELECT COUNT(*) FROM products WHERE barcode = ?', [$s['barcode']])) {
            update('products', ['barcode' => $s['barcode'], 'updated_at' => now()], 'id = ?', [$p['id']]);
        }
        $lineAdded = suggestion_to_request_line($s, $p);
        update('product_suggestions', [
            'status' => 'linked', 'product_id' => $p['id'], 'admin_note' => mb_substr((string)input('admin_note', ''), 0, 255) ?: null,
            'handled_by' => user()['id'], 'handled_at' => now(),
        ], 'id = ?', [$s['id']]);
    });
    notify([(int)$s['user_id']], 'suggestion_done', 'Article trouvé au catalogue : ' . $p['name'],
        'L\'article que vous avez proposé (« ' . $s['name'] . ' ») existe déjà au catalogue sous ce nom.'
            . ($lineAdded ? ' Votre demande de ' . (int)$s['qty'] . ' unité(s) est en cours de traitement.' : ''),
        url('product', ['id' => $p['id']]));
    flash('success', 'Proposition rattachée à « ' . $p['name'] . ' »' . ($lineAdded ? ', demande placée dans « Demandes à traiter »' : '') . '.');
    redirect('admin/suggestions');
}

function admin_suggestion_reject(): void
{
    require_admin();
    $s = admin_suggestion_or_fail(input_int('id'));
    if ($s['status'] === 'pending') {
        $reason = mb_substr(trim((string)input('admin_note', '')), 0, 255) ?: 'Article non retenu par le service achats';
        audit('Proposition refusée', 'suggestion', (int)$s['id'], $s['name'] . ' — ' . $reason);
        update('product_suggestions', ['status' => 'rejected', 'admin_note' => $reason, 'handled_by' => user()['id'], 'handled_at' => now()], 'id = ?', [$s['id']]);
        notify([(int)$s['user_id']], 'suggestion_done', 'Proposition non retenue : ' . $s['name'], 'Motif : ' . $reason, url('requests', ['scope' => 'suggestions']));
        flash('success', 'Proposition refusée, le demandeur a été prévenu.');
    }
    redirect('admin/suggestions');
}
