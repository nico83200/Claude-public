<?php
declare(strict_types=1);

/**
 * Contrats et marchés : prix contractuels par fournisseur (contrat direct ou groupement d'achats),
 * appliqués automatiquement aux articles pendant la durée du contrat, avec alerte avant l'échéance.
 */

/** Groupements d'achats proposés à la saisie (texte libre accepté). */
const BUYING_GROUPS = ['UniHA', 'Resah', 'UGAP', 'CAIH', 'Helpévia', 'AGEPS', 'Groupement régional'];

function contracts_dir(): string
{
    $d = storage_path('contracts');
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** Date limite pour décider du renouvellement (échéance moins le préavis). */
function contract_decision_date(array $c): string
{
    return date('Y-m-d', strtotime($c['end_date'] . ' -' . max(0, (int)$c['notice_days']) . ' days'));
}

/** État du contrat : à venir, en cours, à renouveler (préavis atteint), expiré. */
function contract_status(array $c, ?string $today = null): array
{
    $today ??= date('Y-m-d');
    $days = (int)floor((strtotime($c['end_date']) - strtotime($today)) / 86400);
    if ($c['start_date'] > $today) {
        return ['key' => 'upcoming', 'label' => 'À venir', 'color' => 'blue', 'days' => $days];
    }
    if ($c['end_date'] < $today) {
        return ['key' => 'expired', 'label' => 'Expiré', 'color' => 'gray', 'days' => $days];
    }
    if (contract_decision_date($c) <= $today) {
        return ['key' => 'renew', 'label' => (int)$c['tacit_renewal'] ? 'Reconduction à décider' : 'À renouveler', 'color' => 'amber', 'days' => $days];
    }
    return ['key' => 'active', 'label' => 'En cours', 'color' => 'green', 'days' => $days];
}

/** « dans 45 jours », « demain », « expiré depuis 3 jours »… */
function contract_days_label(int $days): string
{
    return match (true) {
        $days > 1 => 'encore ' . $days . ' jours',
        $days === 1 => 'se termine demain',
        $days === 0 => 'se termine aujourd\'hui',
        default => 'terminé depuis ' . plural(-$days, 'jour', 'jours'),
    };
}

function contract_list(): array
{
    $rows = all('SELECT c.*, s.name AS supplier_name, s.color AS supplier_color,
                        (SELECT COUNT(*) FROM contract_prices cp WHERE cp.contract_id = c.id) AS n_prices
                 FROM contracts c JOIN suppliers s ON s.id = c.supplier_id ORDER BY c.end_date');
    foreach ($rows as &$r) {
        $r['status'] = contract_status($r);
    }
    return $rows;
}

/** Contrat en vigueur qui fixe le prix d'un article (le prix le plus bas si plusieurs). */
function product_contract(int $productId): ?array
{
    $today = date('Y-m-d');
    return one('SELECT c.id, c.name, c.reference, c.buying_group, c.end_date, cp.price
                FROM contract_prices cp JOIN contracts c ON c.id = cp.contract_id
                WHERE cp.product_id = ? AND c.start_date <= ? AND c.end_date >= ?
                ORDER BY cp.price, c.end_date DESC LIMIT 1', [$productId, $today, $today]);
}

/** Prix contractuels en vigueur, par article : [product_id => prix le plus bas]. */
function contract_prices_now(): array
{
    $today = date('Y-m-d');
    $out = [];
    foreach (all('SELECT cp.product_id, MIN(cp.price) AS price FROM contract_prices cp JOIN contracts c ON c.id = cp.contract_id
                  WHERE c.start_date <= ? AND c.end_date >= ? GROUP BY cp.product_id', [$today, $today]) as $r) {
        $out[(int)$r['product_id']] = (float)$r['price'];
    }
    return $out;
}

/**
 * Reporte les prix des contrats en vigueur sur les articles (tarif négocié), avec historique.
 * Appelé à l'enregistrement d'un contrat, après un import de tarifs et chaque nuit (début d'un contrat).
 */
function contracts_apply_prices(): int
{
    $n = 0;
    foreach (contract_prices_now() as $pid => $price) {
        $p = one('SELECT id, catalog_price, negotiated_price FROM products WHERE id = ?', [$pid]);
        if (!$p || ($p['negotiated_price'] !== null && abs((float)$p['negotiated_price'] - $price) < 0.001)) {
            continue;
        }
        update('products', ['negotiated_price' => $price, 'updated_at' => now()], 'id = ?', [$pid]);
        $c = product_contract($pid);
        price_record($pid, (float)$p['catalog_price'], $price, mb_substr('Contrat ' . ($c['buying_group'] ?: ($c['reference'] ?? '')), 0, 30), false); // colonne source : 30 caractères
        $n++;
    }
    if ($n) {
        q('DELETE FROM ai_cache');
    }
    return $n;
}

/**
 * Tâche quotidienne : applique les prix des contrats qui démarrent et prévient le service achats
 * quand le préavis d'un contrat est atteint, puis à son échéance.
 */
function cron_contracts(): int
{
    contracts_apply_prices();
    $today = date('Y-m-d');
    $n = 0;
    foreach (all('SELECT c.*, s.name AS supplier_name FROM contracts c JOIN suppliers s ON s.id = c.supplier_id WHERE c.end_date >= ? OR c.expired_notified_at IS NULL', [$today]) as $c) {
        $st = contract_status($c, $today);
        $label = $c['name'] . ' (' . $c['supplier_name'] . ($c['buying_group'] ? ' · ' . $c['buying_group'] : '') . ')';
        if ($st['key'] === 'renew' && !$c['alerted_at']) {
            notify(admin_ids(), 'contract_renewal', 'Contrat à renouveler : ' . $c['name'],
                $label . ' se termine le ' . date_fr($c['end_date']) . ' (' . contract_days_label($st['days']) . ').'
                . ((int)$c['tacit_renewal'] ? ' Reconduction tacite : décidez avant le ' . date_fr(contract_decision_date($c)) . ' si vous la dénoncez.' : ' Lancez la renégociation ou la consultation.'),
                url('admin/contract', ['id' => $c['id']]));
            update('contracts', ['alerted_at' => now()], 'id = ?', [$c['id']]);
            $n++;
        }
        if ($c['end_date'] < $today && !$c['expired_notified_at']) {
            $count = (int)val('SELECT COUNT(*) FROM contract_prices WHERE contract_id = ?', [$c['id']]);
            notify(admin_ids(), 'contract_renewal', 'Contrat expiré : ' . $c['name'],
                $label . ' est terminé depuis le ' . date_fr($c['end_date']) . '. ' . plural($count, 'article garde', 'articles gardent') . ' le dernier prix contractuel : vérifiez-les ou prolongez le contrat.',
                url('admin/contract', ['id' => $c['id']]));
            update('contracts', ['expired_notified_at' => now()], 'id = ?', [$c['id']]);
            $n++;
        }
    }
    return $n;
}

/** Contrats demandant une action (préavis atteint ou expirés depuis moins de 30 jours) : bandeau du pilotage. */
function contracts_attention(): array
{
    try {
        return array_values(array_filter(contract_list(), fn($c) => $c['status']['key'] === 'renew'
            || ($c['status']['key'] === 'expired' && $c['status']['days'] >= -30)));
    } catch (PDOException) { // base pas encore migrée
        return [];
    }
}

/** Prolonge un contrat : nouvelle période de même durée, prix conservés, alertes remises à zéro. */
function contract_renew(int $id, int $months): ?array
{
    $c = one('SELECT * FROM contracts WHERE id = ?', [$id]);
    if (!$c) {
        return null;
    }
    $months = max(1, min(120, $months));
    $end = date('Y-m-d', strtotime($c['end_date'] . " +$months months"));
    update('contracts', ['end_date' => $end, 'alerted_at' => null, 'expired_notified_at' => null, 'updated_at' => now()], 'id = ?', [$id]);
    contracts_apply_prices();
    return one('SELECT * FROM contracts WHERE id = ?', [$id]);
}

/**
 * Lignes « référence ; prix » collées depuis un tableur ou l'annexe tarifaire.
 * Renvoie [product_id => prix] pour les articles du fournisseur reconnus, et les lignes non reconnues.
 */
function contract_parse_prices(string $text, int $supplierId): array
{
    $byRef = [];
    foreach (all('SELECT id, reference, barcode FROM products WHERE supplier_id = ?', [$supplierId]) as $p) {
        foreach (['reference', 'barcode'] as $k) {
            if ($p[$k] !== null && $p[$k] !== '') {
                $byRef[mb_strtolower(trim($p[$k]))] = (int)$p['id'];
            }
        }
    }
    $prices = [];
    $unknown = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $cells = preg_split('/\t|;|\|/', $line);
        if (count($cells) < 2) {
            $unknown[] = trim($line);
            continue;
        }
        $ref = mb_strtolower(trim($cells[0]));
        $price = parse_number(trim(end($cells)));
        if (isset($byRef[$ref]) && $price !== null && $price > 0) {
            $prices[$byRef[$ref]] = round($price, 2);
        } else {
            $unknown[] = trim($line);
        }
    }
    return [$prices, $unknown];
}
