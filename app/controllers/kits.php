<?php
declare(strict_types=1);

/**
 * Listes types (kits), réapprovisionnement des stocks bas et mode tablette de la réserve.
 */

function kits_index(): void
{
    $u = require_login();
    $center = require_center();
    render('user/kits', ['title' => 'Listes types', 'center' => $center, 'kits' => kits_for($u, (int)$center['id'])]);
}

function kit_or_fail(int $id, int $centerId, bool $edit = false): array
{
    $u = user();
    $k = one('SELECT * FROM kits WHERE id = ?', [$id]);
    $visible = $k && ((int)$k['user_id'] === (int)$u['id'] || ((int)$k['shared'] === 1 && (!$k['center_id'] || (int)$k['center_id'] === $centerId)) || is_admin());
    if (!$visible || ($edit && !kit_can_edit($k))) {
        abort(404, 'Liste introuvable.');
    }
    return $k;
}

function kit_view(): void
{
    require_login();
    $center = require_center();
    $id = input_int('id');
    $kit = $id ? kit_or_fail($id, (int)$center['id']) : null;
    render('user/kit', [
        'title' => $kit ? $kit['name'] : 'Nouvelle liste type', 'center' => $center, 'kit' => $kit,
        'items' => $kit ? kit_items((int)$kit['id'], (int)$center['id']) : [],
        'catalog' => catalog_products((int)$center['id']),
        'centers' => is_admin() ? all('SELECT id, name FROM centers WHERE active = 1 ORDER BY name') : [],
        'canEdit' => !$kit || kit_can_edit($kit),
    ]);
}

function kit_save(): void
{
    $u = require_login();
    $center = require_center();
    $id = input_int('id');
    $kit = $id ? kit_or_fail($id, (int)$center['id'], true) : null;
    $name = mb_substr(trim((string)input('name', '')), 0, 150);
    if ($name === '') {
        flash('error', 'Donnez un nom à la liste.');
        redirect('kit', array_filter(['id' => $id]));
    }
    $data = ['name' => $name, 'description' => mb_substr(trim((string)input('description', '')), 0, 255) ?: null];
    if (is_admin()) {
        $data['shared'] = input('shared') === '1' ? 1 : 0;
        $data['center_id'] = input_int('center_id') ?: null;
    }
    tx(function () use (&$id, $kit, $data, $u, $center) {
        if ($kit) {
            update('kits', $data, 'id = ?', [$id]);
        } else {
            $id = insert('kits', $data + ['user_id' => $u['id'], 'created_at' => now()] + (is_admin() ? [] : ['shared' => 0, 'center_id' => $center['id']]));
        }
        q('DELETE FROM kit_items WHERE kit_id = ?', [$id]);
        foreach ((array)($_POST['qty'] ?? []) as $pid => $qty) {
            if ((int)$qty > 0 && product_visible_for_center((int)$pid, (int)$center['id'])) {
                insert('kit_items', ['kit_id' => $id, 'product_id' => (int)$pid, 'qty' => min(9999, (int)$qty)]);
            }
        }
        if (($add = input_int('add_product')) && product_visible_for_center($add, (int)$center['id'])
            && !val('SELECT COUNT(*) FROM kit_items WHERE kit_id = ? AND product_id = ?', [$id, $add])) {
            insert('kit_items', ['kit_id' => $id, 'product_id' => $add, 'qty' => max(1, input_int('add_qty', 1))]);
        }
    });
    flash('success', 'Liste « ' . $name . ' » enregistrée.');
    redirect('kit', ['id' => $id]);
}

function kit_to_cart(): void
{
    $u = require_login();
    $center = require_center();
    $kit = kit_or_fail(input_int('id'), (int)$center['id']);
    $n = 0;
    foreach (kit_items((int)$kit['id'], (int)$center['id']) as $it) {
        $qty = isset($_POST['qty'][$it['id']]) ? (int)$_POST['qty'][$it['id']] : (int)$it['qty'];
        if ($qty > 0) {
            cart_add((int)$u['id'], (int)$center['id'], (int)$it['id'], $qty);
            $n++;
        }
    }
    flash('success', plural($n, 'article de la liste ajouté', 'articles de la liste ajoutés') . ' au panier.');
    redirect('cart');
}

function kit_delete(): void
{
    require_login();
    $center = require_center();
    $kit = kit_or_fail(input_int('id'), (int)$center['id'], true);
    q('DELETE FROM kits WHERE id = ?', [$kit['id']]);
    flash('success', 'Liste supprimée.');
    redirect('kits');
}

/** Enregistre le contenu du panier comme liste type personnelle. */
function kit_from_cart(): void
{
    $u = require_login();
    $center = require_center();
    $items = cart_items((int)$u['id'], (int)$center['id']);
    if (!$items) {
        flash('error', 'Le panier est vide.');
        redirect('cart');
    }
    $name = mb_substr(trim((string)input('kit_name', '')), 0, 150) ?: 'Ma liste du ' . date('d/m/Y');
    $id = tx(function () use ($items, $name, $u, $center) {
        $id = insert('kits', ['name' => $name, 'user_id' => $u['id'], 'center_id' => $center['id'], 'shared' => 0, 'created_at' => now()]);
        foreach ($items as $it) {
            insert('kit_items', ['kit_id' => $id, 'product_id' => $it['product_id'], 'qty' => $it['qty']]);
        }
        return $id;
    });
    flash('success', 'Panier enregistré comme liste type « ' . $name . ' ».');
    redirect('kit', ['id' => $id]);
}

// ---------------------------------------------------------------- Réapprovisionnement

function stock_reorder(): void
{
    $u = require_login();
    $center = require_center();
    $n = 0;
    foreach (reorder_suggestions((int)$u['id'], (int)$center['id']) as $s) {
        $pid = (int)$s['product_id'];
        $selected = !isset($_POST['pick']) || isset($_POST['pick'][$pid]);
        $qty = isset($_POST['qty'][$pid]) ? (int)$_POST['qty'][$pid] : (int)$s['suggested'];
        if ($selected && $qty > 0) {
            cart_add((int)$u['id'], (int)$center['id'], $pid, $qty);
            $n++;
        }
    }
    flash($n ? 'success' : 'info', $n ? plural($n, 'article à réapprovisionner ajouté', 'articles à réapprovisionner ajoutés') . ' au panier.' : 'Aucun stock bas à réapprovisionner.');
    redirect('cart');
}

// ---------------------------------------------------------------- Mode tablette (réserve)

function stock_quick(): void
{
    require_login();
    $center = require_center();
    render('user/stock_quick', ['title' => 'Inventaire tablette', 'center' => $center], 'layout_quick');
}

/** Inventaire d'un seul article (mode tablette, appel AJAX). */
function stock_count_one(): void
{
    require_login();
    $center = require_center();
    $pid = input_int('product_id');
    if (!product_visible_for_center($pid, (int)$center['id'])) {
        json_response(['error' => 'Article indisponible pour ce centre.'], 404);
    }
    $r = stock_set_counted((int)$center['id'], $pid, max(0, input_int('qty')));
    json_response(['ok' => true] + $r);
}
