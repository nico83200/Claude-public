<?php
declare(strict_types=1);

function catalog_index(): void
{
    $u = require_login();
    $center = require_center();
    $cid = (int)$center['id'];
    $query = (string)input('q', '');
    $category = input_int('cat');
    $supplier = input_int('sup');
    $favOnly = input('fav') === '1';

    $products = catalog_products($cid, ['category' => $category, 'supplier' => $supplier]);
    $pop = product_popularity($cid);
    $favIds = user_favorites((int)$u['id']);

    if ($favOnly) {
        $products = array_values(array_filter($products, fn($p) => in_array((int)$p['id'], $favIds, true)));
    }
    if ($query !== '') {
        $scores = search_local($products, $query, $pop);
        $byId = array_column($products, null, 'id');
        $products = [];
        foreach (array_keys($scores) as $id) {
            $products[] = $byId[$id];
        }
    } else {
        // Les plus demandés d'abord, puis ordre alphabétique
        usort($products, fn($a, $b) => [($pop[$b['id']] ?? 0), $a['name']] <=> [($pop[$a['id']] ?? 0), $b['name']]);
    }

    $categories = all('SELECT c.*, (SELECT COUNT(*) FROM products p JOIN suppliers s ON s.id = p.supplier_id
                                     WHERE p.category_id = c.id AND p.active = 1 AND ' . supplier_visible_sql('s') . ') AS nb
                       FROM categories c ORDER BY c.position, c.name', [$cid]);
    $suppliers = all('SELECT s.id, s.name, s.color FROM suppliers s WHERE ' . supplier_visible_sql('s') . ' ORDER BY s.name', [$cid]);

    render('user/catalog', [
        'title' => 'Catalogue', 'center' => $center, 'products' => $products, 'query' => $query,
        'category' => $category, 'supplier' => $supplier, 'favOnly' => $favOnly,
        'categories' => $categories, 'suppliers' => $suppliers, 'favIds' => $favIds,
        'deadlinesBySupplier' => supplier_deadlines($cid), 'aiEnabled' => ai_available(),
    ]);
}

function catalog_product(): void
{
    $u = require_login();
    $center = require_center();
    $p = one('SELECT p.*, s.name AS supplier_name, s.color AS supplier_color, s.delivery_delay, c.name AS category_name, c.color AS category_color
              FROM products p JOIN suppliers s ON s.id = p.supplier_id LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.id = ?', [input_int('id')]);
    if (!$p || (!is_admin() && !product_visible_for_center((int)$p['id'], (int)$center['id']))) {
        abort(404, 'Article introuvable pour ce centre.');
    }
    $similar = [];
    if ($p['category_id']) {
        $similar = array_slice(array_filter(
            catalog_products((int)$center['id'], ['category' => (int)$p['category_id']]),
            fn($x) => (int)$x['id'] !== (int)$p['id']
        ), 0, 4);
    }
    $history = all('SELECT rl.qty, rl.created_at, u.first_name, u.last_name FROM request_lines rl
                    JOIN requests r ON r.id = rl.request_id JOIN users u ON u.id = r.user_id
                    WHERE rl.product_id = ? AND rl.center_id = ? ORDER BY rl.created_at DESC LIMIT 5', [$p['id'], $center['id']]);
    render('user/product', [
        'title' => $p['name'], 'center' => $center, 'p' => $p, 'similar' => $similar, 'history' => $history,
        'isFav' => in_array((int)$p['id'], user_favorites((int)$u['id']), true),
    ]);
}

/** Données JSON d'un article pour l'affichage dynamique. */
function product_json(array $p, array $favIds = []): array
{
    $show = show_prices();
    return [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'reference' => $p['reference'],
        'barcode' => $p['barcode'] ?? null,
        'unit' => $p['unit'],
        'description' => mb_substr((string)$p['description'], 0, 160),
        'image' => product_image_url($p['image']),
        'supplier' => $p['supplier_name'],
        'supplier_color' => $p['supplier_color'],
        'category' => $p['category_name'],
        'category_color' => $p['category_color'],
        'category_icon' => $p['category_icon'] ?? 'box',
        'price' => $show ? money(effective_price($p)) : null,
        'catalog_price' => ($show && $p['negotiated_price'] !== null && (float)$p['negotiated_price'] > 0 && (float)$p['negotiated_price'] < (float)$p['catalog_price']) ? money($p['catalog_price']) : null,
        'min_qty' => (int)$p['min_qty'],
        'fav' => in_array((int)$p['id'], $favIds, true),
        'url' => url('product', ['id' => $p['id']]),
    ];
}

/** Suggestions instantanées pendant la frappe. */
function api_search(): void
{
    $u = require_login();
    $center = current_center();
    if (!$center) {
        json_response(['results' => []]);
    }
    $q = (string)input('q', '');
    if (mb_strlen($q) < 2) {
        json_response(['results' => []]);
    }
    $products = catalog_products((int)$center['id']);
    $scores = search_local($products, $q, product_popularity((int)$center['id']));
    $byId = array_column($products, null, 'id');
    $fav = user_favorites((int)$u['id']);
    $out = [];
    foreach (array_slice(array_keys($scores), 0, (int)input('limit', 8)) as $id) {
        $out[] = product_json($byId[$id], $fav);
    }
    json_response(['results' => $out, 'total' => count($scores)]);
}

/** Recherche assistée par IA (langage naturel). */
function api_ai_search(): void
{
    $u = require_login();
    $center = current_center();
    $q = (string)input('q', '');
    if (!$center || mb_strlen($q) < 2) {
        json_response(['available' => false]);
    }
    if (!ai_available()) {
        json_response(['available' => false]);
    }
    // Limitation simple : 20 requêtes IA par minute et par session
    $now = time();
    $_SESSION['ai_calls'] = array_values(array_filter($_SESSION['ai_calls'] ?? [], fn($t) => $t > $now - 60));
    if (count($_SESSION['ai_calls']) >= 20) {
        json_response(['available' => true, 'error' => 'Trop de recherches IA en peu de temps, patientez quelques secondes.']);
    }
    $_SESSION['ai_calls'][] = $now;
    session_write_close(); // ne bloque pas les autres requêtes pendant l'appel IA

    $products = catalog_products((int)$center['id']);
    $r = ai_search($products, $q);
    if ($r === null) {
        json_response(['available' => true, 'error' => 'L\'assistant IA est momentanément indisponible. Les résultats ci-dessous viennent de la recherche classique.']);
    }
    $byId = array_column($products, null, 'id');
    $fav = user_favorites((int)$u['id']);
    $results = [];
    foreach ($r['ids'] as $id) {
        if (isset($byId[$id])) {
            $results[] = product_json($byId[$id], $fav);
        }
    }
    json_response(['available' => true, 'message' => $r['message'], 'related' => $r['related'], 'results' => $results]);
}

function favorite_toggle(): void
{
    $u = require_login();
    $pid = input_int('id');
    if (val('SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?', [$u['id'], $pid])) {
        q('DELETE FROM favorites WHERE user_id = ? AND product_id = ?', [$u['id'], $pid]);
        $fav = false;
    } else {
        if (!val('SELECT COUNT(*) FROM products WHERE id = ?', [$pid])) {
            json_response(['error' => 'introuvable'], 404);
        }
        insert('favorites', ['user_id' => $u['id'], 'product_id' => $pid]);
        $fav = true;
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['fav' => $fav]);
    }
    redirect_back('catalog');
}
