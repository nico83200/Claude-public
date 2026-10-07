<?php
declare(strict_types=1);

/**
 * Nettoyage des données : jeu de démonstration, activité de test et suppression de comptes.
 */

const DEMO_CENTER_CODES = ['TIL', 'PORT', 'LG'];
const DEMO_SUPPLIER_NAMES = ['MédiDistrib', 'Hygiène Pro Sud', 'Bureau Express', 'KinéSport Équipement', 'Pause Café Services'];
const DEMO_KIT_NAMES = ['Kit salle de soins', 'Commande mensuelle accueil'];

/** Éléments du jeu de démonstration encore présents : ['centers' => ids, 'suppliers' => ids, 'users' => ids]. */
function demo_scope(): array
{
    $ids = fn(string $sql, array $p) => array_map('intval', array_column(all($sql, $p), 'id'));
    return [
        'centers'   => $ids('SELECT id FROM centers WHERE code IN ' . in_list(DEMO_CENTER_CODES)
            . " AND name IN ('Centre de santé Les Tilleuls', 'Centre médical du Port', 'Maison de santé La Garde')", DEMO_CENTER_CODES),
        'suppliers' => $ids('SELECT id FROM suppliers WHERE name IN ' . in_list(DEMO_SUPPLIER_NAMES) . " AND email LIKE '%.example'", DEMO_SUPPLIER_NAMES),
        'users'     => $ids("SELECT id FROM users WHERE email LIKE '%@demo.fr' AND role <> 'admin'", []),
    ];
}

function demo_present(): bool
{
    $s = demo_scope();
    return (bool)($s['centers'] || $s['suppliers'] || $s['users']);
}

/** Compte ce que la suppression des données de démonstration retirerait. */
function demo_summary(): array
{
    $s = demo_scope();
    [$c, $sup, $u] = [$s['centers'], $s['suppliers'], $s['users']];
    return [
        'centres'      => count($c),
        'fournisseurs' => count($sup),
        'articles'     => (int)val('SELECT COUNT(*) FROM products WHERE supplier_id IN ' . in_list($sup), $sup),
        'comptes'      => count($u),
        'demandes'     => (int)val('SELECT COUNT(*) FROM requests WHERE center_id IN ' . in_list($c) . ' OR user_id IN ' . in_list($u), [...$c, ...$u]),
        'bons'         => (int)val('SELECT COUNT(*) FROM purchase_orders WHERE center_id IN ' . in_list($c) . ' OR supplier_id IN ' . in_list($sup), [...$c, ...$sup]),
    ];
}

/** Exécute une requête « ... IN (?) » seulement si la liste n'est pas vide. */
function purge_q(string $sql, array $ids, array $extra = []): void
{
    if ($ids) {
        q(str_replace('{IN}', in_list($ids), $sql), [...$ids, ...$extra]);
    }
}

function purge_ids(string $sql, array $params): array
{
    return array_values(array_unique(array_map('intval', array_column(all($sql, $params), 'id'))));
}

/**
 * Supprime définitivement des centres, fournisseurs (avec leurs articles) et comptes,
 * ainsi que toutes les données qui en dépendent (demandes, bons, stocks, budgets…).
 */
function purge_entities(array $centers, array $suppliers, array $users): void
{
    $products = $suppliers ? purge_ids('SELECT id FROM products WHERE supplier_id IN ' . in_list($suppliers), $suppliers) : [];

    // Bons de commande des centres / fournisseurs supprimés, et lignes portant sur leurs articles
    $pos = purge_ids('SELECT id FROM purchase_orders WHERE center_id IN ' . in_list($centers) . ' OR supplier_id IN ' . in_list($suppliers), [...$centers, ...$suppliers]);
    purge_q('UPDATE request_lines SET purchase_order_id = NULL WHERE purchase_order_id IN {IN}', $pos);
    purge_q('UPDATE stock_movements SET purchase_order_id = NULL WHERE purchase_order_id IN {IN}', $pos);
    purge_q('DELETE FROM po_history WHERE purchase_order_id IN {IN}', $pos);
    purge_q('DELETE FROM purchase_order_lines WHERE purchase_order_id IN {IN}', $pos);
    purge_q('DELETE FROM purchase_orders WHERE id IN {IN}', $pos);
    purge_q('DELETE FROM purchase_order_lines WHERE product_id IN {IN}', $products);

    // Demandes : celles des centres / comptes supprimés, et lignes sur les articles supprimés
    $reqs = purge_ids('SELECT id FROM requests WHERE center_id IN ' . in_list($centers) . ' OR user_id IN ' . in_list($users), [...$centers, ...$users]);
    $touched = purge_ids('SELECT DISTINCT request_id AS id FROM request_lines WHERE product_id IN ' . in_list($products) . ' OR supplier_id IN ' . in_list($suppliers), [...$products, ...$suppliers]);
    purge_q('DELETE FROM request_lines WHERE request_id IN {IN}', $reqs);
    purge_q('DELETE FROM request_lines WHERE product_id IN {IN}', $products);
    purge_q('DELETE FROM request_lines WHERE supplier_id IN {IN}', $suppliers);
    purge_q('UPDATE product_suggestions SET request_id = NULL WHERE request_id IN {IN}', $reqs);
    purge_q('DELETE FROM requests WHERE id IN {IN}', $reqs);
    // Demandes vidées par la suppression de leurs lignes (et sans article hors catalogue)
    purge_q('DELETE FROM requests WHERE id IN {IN} AND NOT EXISTS (SELECT 1 FROM request_lines l WHERE l.request_id = requests.id)
             AND NOT EXISTS (SELECT 1 FROM product_suggestions s WHERE s.request_id = requests.id)', $touched);

    // Suggestions d'articles
    purge_q('DELETE FROM product_suggestions WHERE center_id IN {IN}', $centers);
    purge_q('DELETE FROM product_suggestions WHERE user_id IN {IN}', $users);
    purge_q('UPDATE product_suggestions SET product_id = NULL WHERE product_id IN {IN}', $products);
    purge_q('UPDATE product_suggestions SET handled_by = NULL WHERE handled_by IN {IN}', $users);

    // Articles
    foreach (['cart_items', 'favorites', 'stock', 'stock_movements', 'kit_items', 'price_history'] as $t) {
        purge_q("DELETE FROM $t WHERE product_id IN {IN}", $products);
    }
    purge_q('DELETE FROM products WHERE id IN {IN}', $products);

    // Fournisseurs
    purge_q('DELETE FROM supplier_centers WHERE supplier_id IN {IN}', $suppliers);
    purge_q('DELETE FROM deadlines WHERE supplier_id IN {IN}', $suppliers);
    purge_q('DELETE FROM suppliers WHERE id IN {IN}', $suppliers);

    // Centres
    foreach (['user_centers', 'supplier_centers', 'cart_items', 'stock', 'stock_movements', 'budgets', 'deadlines', 'kits'] as $t) {
        if ($t === 'kits') {
            purge_q('DELETE FROM kit_items WHERE kit_id IN (SELECT id FROM kits WHERE center_id IN {IN})', $centers);
        }
        purge_q("DELETE FROM $t WHERE center_id IN {IN}", $centers);
    }
    purge_q('DELETE FROM centers WHERE id IN {IN}', $centers);

    // Comptes
    purge_q('DELETE FROM kit_items WHERE kit_id IN (SELECT id FROM kits WHERE user_id IN {IN})', $users);
    foreach (['user_centers', 'cart_items', 'favorites', 'notifications', 'password_resets', 'kits'] as $t) {
        purge_q("DELETE FROM $t WHERE user_id IN {IN}", $users);
    }
    foreach (['purchase_orders' => ['created_by', 'ordered_by'], 'purchase_order_lines' => ['received_by'], 'po_history' => ['user_id'],
              'stock_movements' => ['user_id'], 'deadlines' => ['created_by'], 'price_history' => ['user_id'],
              'audit_log' => ['user_id'], 'update_history' => ['user_id']] as $t => $cols) {
        foreach ($cols as $col) {
            purge_q("UPDATE $t SET $col = NULL WHERE $col IN {IN}", $users);
        }
    }
    if ($users) {
        purge_q('DELETE FROM mail_queue WHERE sent_at IS NULL AND to_email IN (SELECT email FROM users WHERE id IN {IN})', $users);
    }
    purge_q('DELETE FROM users WHERE id IN {IN}', $users);
    q('DELETE FROM ai_cache');
}

/** Efface les comptes anonymisés qui n'ont plus aucune demande (après un nettoyage). */
function purge_orphan_deleted_users(): void
{
    $ids = purge_ids('SELECT id FROM users u WHERE deleted_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM requests r WHERE r.user_id = u.id)', []);
    purge_entities([], [], $ids);
}

/** Supprime le jeu de démonstration installé avec l'application. Renvoie le résumé de ce qui a été retiré. */
function demo_purge(): array
{
    $summary = demo_summary();
    $s = demo_scope();
    tx(function () use ($s) {
        purge_entities($s['centers'], $s['suppliers'], $s['users']);
        purge_orphan_deleted_users();
        // Listes types de démonstration devenues vides
        q('DELETE FROM kits WHERE name IN ' . in_list(DEMO_KIT_NAMES) . ' AND NOT EXISTS (SELECT 1 FROM kit_items i WHERE i.kit_id = kits.id)', DEMO_KIT_NAMES);
        if (str_contains((string)setting('company_name'), '(démo)')) {
            set_setting('company_name', '');
            set_setting('company_address', '');
        }
        // Notifications des administrateurs nées des données de démonstration
        q("DELETE FROM notifications WHERE body LIKE '%@demo.fr%' OR title LIKE '%Les Tilleuls%' OR title LIKE '%du Port%' OR title LIKE '%La Garde%'
           OR body LIKE '%Les Tilleuls%' OR body LIKE '%du Port%' OR body LIKE '%La Garde%'");
    });
    return $summary;
}

/**
 * Efface toute l'activité (demandes, paniers, bons de commande, stocks, notifications, propositions d'articles)
 * en conservant centres, fournisseurs, catalogue, comptes, budgets, listes types et paramètres.
 */
function activity_purge(): void
{
    tx(function () {
        foreach (['po_history', 'purchase_order_lines', 'stock_movements', 'request_lines', 'product_suggestions', 'purchase_orders', 'requests',
                  'cart_items', 'stock', 'notifications', 'mail_queue', 'ai_cache'] as $t) {
            q("DELETE FROM $t");
        }
        q('UPDATE budgets SET alert_sent = 0');
        purge_orphan_deleted_users();
    });
}

/**
 * Supprime un compte. Sans historique, il est effacé ; s'il a passé des demandes, il est anonymisé
 * (nom, e-mail, téléphone effacés, connexion impossible) pour conserver l'historique des commandes.
 * Renvoie 'deleted', 'anonymized' ou un message d'erreur.
 */
function user_delete(int $id, int $byAdminId): string
{
    $u = one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
    if (!$u) {
        return 'Compte introuvable.';
    }
    if ($id === $byAdminId) {
        return 'Vous ne pouvez pas supprimer votre propre compte.';
    }
    if ($u['role'] === 'admin' && (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND deleted_at IS NULL AND id <> ?", [$id]) === 0) {
        return 'Impossible de supprimer le dernier administrateur.';
    }
    $hasHistory = (int)val('SELECT COUNT(*) FROM requests WHERE user_id = ?', [$id]) > 0;
    return tx(function () use ($u, $id, $hasHistory) {
        if (!$hasHistory) {
            purge_entities([], [], [$id]);
            return 'deleted';
        }
        q('DELETE FROM kit_items WHERE kit_id IN (SELECT id FROM kits WHERE user_id = ? AND shared = 0)', [$id]);
        foreach (['user_centers', 'cart_items', 'favorites', 'notifications', 'password_resets'] as $t) {
            q("DELETE FROM $t WHERE user_id = ?", [$id]);
        }
        q('DELETE FROM kits WHERE user_id = ? AND shared = 0', [$id]);
        q('DELETE FROM mail_queue WHERE sent_at IS NULL AND to_email = ?', [$u['email']]);
        update('users', [
            'first_name' => 'Ancien compte', 'last_name' => '#' . $id, 'email' => 'supprime-' . $id . '-' . bin2hex(random_bytes(3)) . '@compte.invalid',
            'phone' => null, 'job' => $u['job'], 'status' => 'disabled', 'role' => 'user', 'requested_centers' => null, 'notify_email' => 0,
            'notify_prefs' => null, 'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'deleted_at' => now(),
        ], 'id = ?', [$id]);
        return 'anonymized';
    });
}

/**
 * Supprime un article. S'il figure dans des demandes ou des bons de commande, il est seulement
 * masqué (désactivé) pour préserver l'historique. Renvoie 'deleted', 'archived' ou un message d'erreur.
 */
function product_delete(int $id): string
{
    $p = one('SELECT id, image FROM products WHERE id = ?', [$id]);
    if (!$p) {
        return 'Article introuvable.';
    }
    $used = (int)val('SELECT COUNT(*) FROM request_lines WHERE product_id = ?', [$id])
        + (int)val('SELECT COUNT(*) FROM purchase_order_lines WHERE product_id = ?', [$id]);
    if ($used) {
        update('products', ['active' => 0, 'updated_at' => now()], 'id = ?', [$id]);
        q('DELETE FROM cart_items WHERE product_id = ?', [$id]);
        q('DELETE FROM favorites WHERE product_id = ?', [$id]);
        q('DELETE FROM kit_items WHERE product_id = ?', [$id]);
        return 'archived';
    }
    tx(function () use ($id) {
        foreach (['cart_items', 'favorites', 'stock', 'stock_movements', 'kit_items', 'price_history'] as $t) {
            q("DELETE FROM $t WHERE product_id = ?", [$id]);
        }
        q('UPDATE product_suggestions SET product_id = NULL WHERE product_id = ?', [$id]);
        q('DELETE FROM products WHERE id = ?', [$id]);
    });
    // Photo effacée seulement si aucun autre article ni proposition ne l'utilise
    if ($p['image'] && !val('SELECT COUNT(*) FROM products WHERE image = ?', [$p['image']]) && !val('SELECT COUNT(*) FROM product_suggestions WHERE image = ?', [$p['image']])) {
        delete_image($p['image']);
    }
    return 'deleted';
}
