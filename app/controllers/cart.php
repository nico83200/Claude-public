<?php
declare(strict_types=1);

function cart_index(): void
{
    $u = require_login();
    $center = require_center();
    $items = cart_items((int)$u['id'], (int)$center['id']);
    $bySupplier = [];
    $total = 0.0;
    foreach ($items as $it) {
        $sid = (int)$it['supplier_id'];
        $bySupplier[$sid]['name'] = $it['supplier_name'];
        $bySupplier[$sid]['color'] = $it['supplier_color'];
        $bySupplier[$sid]['items'][] = $it;
        $line = effective_price($it) * (int)$it['qty'];
        $bySupplier[$sid]['total'] = ($bySupplier[$sid]['total'] ?? 0) + $line;
        $total += $line;
    }
    render('user/cart', [
        'title' => 'Mon panier', 'center' => $center, 'groups' => $bySupplier, 'total' => $total,
        'count' => count($items), 'offCatalog' => cart_suggestions((int)$u['id'], (int)$center['id']), 'deadlinesBySupplier' => supplier_deadlines((int)$center['id']),
    ]);
}

function cart_add_action(): void
{
    $u = require_login();
    $center = require_center();
    $pid = input_int('product_id');
    $qty = input_int('qty', 1);
    $ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    $p = product_visible_for_center($pid, (int)$center['id']);
    if (!$p) {
        $ajax ? json_response(['error' => 'Article indisponible pour ce centre.'], 404) : abort(404);
    }
    cart_add((int)$u['id'], (int)$center['id'], $pid, max($qty, 1));
    $count = cart_count((int)$u['id'], (int)$center['id']);
    if ($ajax) {
        json_response(['ok' => true, 'count' => $count, 'name' => $p['name']]);
    }
    flash('success', '« ' . $p['name'] . ' » ajouté au panier.');
    redirect_back('catalog');
}

function cart_update_action(): void
{
    $u = require_login();
    $center = require_center();
    foreach ((array)($_POST['qty'] ?? []) as $id => $qty) {
        $qty = (int)$qty;
        if ($qty <= 0) {
            q('DELETE FROM cart_items WHERE id = ? AND user_id = ?', [(int)$id, $u['id']]);
        } else {
            update('cart_items', ['qty' => min($qty, 9999)], 'id = ? AND user_id = ?', [(int)$id, $u['id']]);
        }
    }
    foreach ((array)($_POST['off_qty'] ?? []) as $id => $qty) {
        update('product_suggestions', ['qty' => max(1, min(9999, (int)$qty))], 'id = ? AND user_id = ? AND in_cart = 1', [(int)$id, $u['id']]);
    }
    foreach ((array)($_POST['comment'] ?? []) as $id => $c) {
        update('cart_items', ['comment' => mb_substr(trim((string)$c), 0, 255) ?: null], 'id = ? AND user_id = ?', [(int)$id, $u['id']]);
    }
    if (input('then') === 'submit') {
        cart_submit_action();
        return;
    }
    flash('success', 'Panier mis à jour.');
    redirect('cart', ['c' => $center['id']]);
}

function cart_remove_action(): void
{
    $u = require_login();
    q('DELETE FROM cart_items WHERE id = ? AND user_id = ?', [input_int('id'), $u['id']]);
    redirect('cart');
}

function cart_submit_action(): void
{
    $u = require_login();
    $center = require_center();
    try {
        $id = cart_submit((int)$u['id'], (int)$center['id'], (string)input('request_comment', ''), input('urgent') === '1');
        $n = (int)val('SELECT COUNT(*) FROM request_lines WHERE request_id = ?', [$id]);
        $off = all('SELECT name, qty FROM product_suggestions WHERE request_id = ?', [$id]);
        if ($off) {
            notify(admin_ids(), 'suggestion_new', plural(count($off), 'article hors catalogue proposé', 'articles hors catalogue proposés') . ' — ' . $center['name'],
                $u['first_name'] . ' ' . $u['last_name'] . ' demande : ' . implode(', ', array_map(fn($o) => $o['name'] . ($o['qty'] ? ' (× ' . $o['qty'] . ')' : ''), $off)) . '.',
                url('admin/suggestions'));
        }
        $req = one('SELECT * FROM requests WHERE id = ?', [$id]);
        if ($n && $req['approval_status'] === 'pending') {
            notify(center_manager_ids((int)$center['id'], (int)$u['id']), 'approval_needed', 'Demande à valider — ' . $u['first_name'] . ' ' . $u['last_name'],
                'Demande n°' . $id . ' (' . $center['name'] . ') : ' . plural($n, 'article', 'articles') . ', '
                    . money(val('SELECT SUM(qty*unit_price) FROM request_lines WHERE request_id = ?', [$id])) . ' HT.', url('approvals'));
            flash('success', 'Votre demande n°' . $id . ' a été transmise à votre responsable de centre pour validation, puis partira au service achats.');
            redirect('requests');
        }
        if ($n) {
            notify(admin_ids(), 'request_new',
                (input('urgent') === '1' ? '[URGENT] ' : '') . 'Nouvelle demande — ' . $center['name'],
                $u['first_name'] . ' ' . $u['last_name'] . ($u['job'] ? ' (' . $u['job'] . ')' : '') . ' a demandé ' . plural($n, 'article', 'articles') . '.'
                    . (input('request_comment') ? "\nCommentaire : " . input('request_comment') : ''),
                url('admin/requests', ['center' => $center['id']]));
        }
        flash('success', 'Votre demande n°' . $id . ' a bien été transmise au service achats. Merci !');
        redirect('requests');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('cart');
    }
}
