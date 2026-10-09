<?php
declare(strict_types=1);

/**
 * Tableau de bord de direction : dépenses engagées, économies réalisées grâce aux tarifs négociés,
 * répartition par centre, catégorie et fournisseur, évolution sur 12 mois. Rapport PDF mensuel.
 *
 * Une dépense est comptée à la date de commande (bons « commandé », « reçu partiellement », « reçu »),
 * frais de port inclus. Les économies comparent le tarif catalogue au tarif obtenu, ligne par ligne.
 */

const REPORT_PERIODS = [
    'month' => 'Mois en cours', 'last_month' => 'Mois dernier', 'quarter' => 'Trimestre en cours',
    'year' => 'Année en cours', '12m' => '12 derniers mois',
];

/** Bornes [début, fin exclue] d'une période, et la période précédente de même durée. */
function report_range(string $period, ?int $now = null): array
{
    $now ??= time();
    [$from, $to] = match ($period) {
        'last_month' => [date('Y-m-01', strtotime('first day of last month', $now)), date('Y-m-01', $now)],
        'quarter' => [date('Y-', $now) . sprintf('%02d', intdiv((int)date('n', $now) - 1, 3) * 3 + 1) . '-01', date('Y-m-d', strtotime('+1 day', $now))],
        'year' => [date('Y-01-01', $now), date('Y-m-d', strtotime('+1 day', $now))],
        '12m' => [date('Y-m-01', strtotime('-11 months', strtotime(date('Y-m-01', $now)))), date('Y-m-d', strtotime('+1 day', $now))],
        default => [date('Y-m-01', $now), date('Y-m-d', strtotime('+1 day', $now))],
    };
    $days = (int)round((strtotime($to) - strtotime($from)) / 86400);
    $prevFrom = $period === 'last_month' || $period === 'month'
        ? date('Y-m-01', strtotime('-1 month', strtotime($from)))
        : date('Y-m-d', strtotime("-$days days", strtotime($from)));
    $prevTo = $period === 'month' ? date('Y-m-d', strtotime('-1 month', strtotime($to))) : $from;
    return ['from' => $from, 'to' => $to, 'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'label' => REPORT_PERIODS[$period] ?? $period];
}

/** Lignes de commande engagées sur une période (avec centre, fournisseur, catégorie). */
function report_lines(string $from, string $to, ?int $centerId = null): array
{
    $sql = "SELECT l.qty, l.unit_price, l.catalog_price, l.label, l.product_id, po.id AS po_id, po.center_id, po.supplier_id,
                   c.name AS center_name, c.color AS center_color, s.name AS supplier_name, cat.name AS category_name, cat.id AS category_id
            FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id
            JOIN centers c ON c.id = po.center_id JOIN suppliers s ON s.id = po.supplier_id
            LEFT JOIN products p ON p.id = l.product_id LEFT JOIN categories cat ON cat.id = p.category_id
            WHERE po.status IN ('commande', 'partiel', 'recu') AND COALESCE(po.ordered_at, po.created_at) >= ? AND COALESCE(po.ordered_at, po.created_at) < ?";
    $params = [$from, $to];
    if ($centerId) {
        $sql .= ' AND po.center_id = ?';
        $params[] = $centerId;
    }
    return all($sql, $params);
}

function report_shipping(string $from, string $to, ?int $centerId = null): float
{
    return (float)val("SELECT COALESCE(SUM(shipping_fee), 0) FROM purchase_orders WHERE status IN ('commande', 'partiel', 'recu')
        AND COALESCE(ordered_at, created_at) >= ? AND COALESCE(ordered_at, created_at) < ?" . ($centerId ? ' AND center_id = ?' : ''),
        $centerId ? [$from, $to, $centerId] : [$from, $to]);
}

/** Agrégats de la période : totaux, économies, répartitions triées (montant décroissant). */
function report_summary(string $from, string $to, ?int $centerId = null): array
{
    $lines = report_lines($from, $to, $centerId);
    $sum = ['spent' => 0.0, 'catalog' => 0.0, 'pos' => [], 'centers' => [], 'categories' => [], 'suppliers' => [], 'products' => []];
    foreach ($lines as $l) {
        $amount = (int)$l['qty'] * (float)$l['unit_price'];
        $catalog = (int)$l['qty'] * max((float)$l['catalog_price'], (float)$l['unit_price']);
        $sum['spent'] += $amount;
        $sum['catalog'] += $catalog;
        $sum['pos'][(int)$l['po_id']] = true;
        foreach ([
            'centers' => [(int)$l['center_id'], $l['center_name'], $l['center_color']],
            'categories' => [(int)($l['category_id'] ?? 0), $l['category_name'] ?: 'Sans catégorie', null],
            'suppliers' => [(int)$l['supplier_id'], $l['supplier_name'], null],
            'products' => [(int)($l['product_id'] ?? 0) ?: $l['label'], $l['label'], null],
        ] as $dim => [$key, $name, $color]) {
            $sum[$dim][$key] ??= ['name' => $name, 'color' => $color, 'amount' => 0.0, 'savings' => 0.0, 'qty' => 0];
            $sum[$dim][$key]['amount'] += $amount;
            $sum[$dim][$key]['savings'] += $catalog - $amount;
            $sum[$dim][$key]['qty'] += (int)$l['qty'];
        }
    }
    foreach (['centers', 'categories', 'suppliers', 'products'] as $dim) {
        uasort($sum[$dim], fn($a, $b) => $b['amount'] <=> $a['amount']);
    }
    $shipping = report_shipping($from, $to, $centerId);
    return [
        'spent' => round($sum['spent'] + $shipping, 2), 'goods' => round($sum['spent'], 2), 'shipping' => round($shipping, 2),
        'savings' => round($sum['catalog'] - $sum['spent'], 2),
        'savings_pct' => $sum['catalog'] > 0 ? round(($sum['catalog'] - $sum['spent']) / $sum['catalog'] * 100, 1) : 0.0,
        'orders' => count($sum['pos']),
        'centers' => array_values($sum['centers']), 'categories' => array_values($sum['categories']),
        'suppliers' => array_values($sum['suppliers']), 'products' => array_slice(array_values($sum['products']), 0, 10),
    ];
}

/** Dépenses engagées par mois sur les 12 derniers mois (mois en cours inclus). */
function report_monthly(?int $centerId = null, ?int $now = null): array
{
    $now ??= time();
    $out = [];
    for ($i = 11; $i >= 0; $i--) {
        $start = date('Y-m-01', strtotime("-$i months", strtotime(date('Y-m-01', $now))));
        $end = date('Y-m-01', strtotime('+1 month', strtotime($start)));
        $goods = (float)val("SELECT COALESCE(SUM(l.qty * l.unit_price), 0) FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id
            WHERE po.status IN ('commande', 'partiel', 'recu') AND COALESCE(po.ordered_at, po.created_at) >= ? AND COALESCE(po.ordered_at, po.created_at) < ?"
            . ($centerId ? ' AND po.center_id = ?' : ''), $centerId ? [$start, $end, $centerId] : [$start, $end]);
        $out[] = ['month' => substr($start, 0, 7), 'amount' => round($goods + report_shipping($start, $end, $centerId), 2)];
    }
    return $out;
}

/** Variation en % entre deux montants (null si pas de base de comparaison). */
function report_delta(float $now, float $before): ?float
{
    return $before > 0 ? round(($now / $before - 1) * 100, 1) : null;
}

function month_fr(string $ym, bool $short = false): string
{
    $m = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $name = $m[(int)substr($ym, 5, 2) - 1];
    return $short ? mb_substr($name, 0, $name === 'juin' || $name === 'juillet' ? 4 : 3) . '.' : $name . ' ' . substr($ym, 0, 4);
}

/** Données complètes du tableau de bord pour une période. */
function report_data(string $period, ?int $centerId = null, ?int $now = null): array
{
    $r = report_range($period, $now);
    $cur = report_summary($r['from'], $r['to'], $centerId);
    $prev = report_summary($r['prev_from'], $r['prev_to'], $centerId);
    $year = (int)date('Y', $now ?? time());
    $budgets = [];
    foreach (all('SELECT c.id, c.name, c.color FROM centers c WHERE c.active = 1' . ($centerId ? ' AND c.id = ' . (int)$centerId : '') . ' ORDER BY c.name') as $c) {
        $b = budget_status((int)$c['id'], $year);
        if ($b['defined']) {
            $budgets[] = ['name' => $c['name'], 'color' => $c['color'], 'amount' => $b['amount'], 'spent' => $b['spent'], 'pct' => $b['amount'] > 0 ? round($b['spent'] / $b['amount'] * 100) : 0];
        }
    }
    return [
        'range' => $r, 'cur' => $cur, 'prev' => $prev,
        'delta' => ['spent' => report_delta($cur['spent'], $prev['spent']), 'savings' => report_delta($cur['savings'], $prev['savings'])],
        'monthly' => report_monthly($centerId, $now), 'budgets' => $budgets,
        'stock_value' => (float)val('SELECT COALESCE(SUM(st.qty * COALESCE(NULLIF(p.negotiated_price, 0), p.catalog_price)), 0) FROM stock st JOIN products p ON p.id = st.product_id'
            . ($centerId ? ' WHERE st.center_id = ' . (int)$centerId : '')),
    ];
}

// ---------------------------------------------------------------- Rapport PDF mensuel

function reports_dir(): string
{
    $d = storage_path('reports');
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
        @file_put_contents($d . '/.htaccess', "Require all denied\n");
    }
    return $d;
}

/** Rapport PDF d'un mois (AAAA-MM) : synthèse, répartitions, évolution, budgets. Renvoie le contenu PDF. */
function report_pdf(string $month): string
{
    $now = strtotime($month . '-15');
    $d = report_data('month', null, $now);
    // Mois complet (et non « jusqu'à aujourd'hui »)
    $from = $month . '-01';
    $to = date('Y-m-01', strtotime('+1 month', strtotime($from)));
    $cur = report_summary($from, $to);
    $prevFrom = date('Y-m-01', strtotime('-1 month', strtotime($from)));
    $prev = report_summary($prevFrom, $from);
    $pdf = new SimplePdf();
    $pdf->addPage();
    $company = setting('company_name') ?: app_name();
    $pdf->text(15, 18, $company, 9, false, [100, 100, 120]);
    $pdf->text(15, 28, 'Rapport achats — ' . month_fr($month), 18, true, [30, 35, 53]);
    $pdf->text(15, 35, 'Dépenses engagées (bons commandés), frais de port inclus. Généré le ' . date('d/m/Y') . ' par ' . app_name() . '.', 8.5, false, [100, 100, 120]);
    // Indicateurs
    $kpis = [
        ['Dépenses', money($cur['spent']), report_delta($cur['spent'], $prev['spent'])],
        ['Économies négociées', money($cur['savings']), null],
        ['Bons de commande', (string)$cur['orders'], null],
        ['Valeur des stocks', money($d['stock_value']), null],
    ];
    foreach ($kpis as $i => [$label, $value, $delta]) {
        $x = 15 + $i * 46;
        $pdf->rect($x, 42, 43, 22, [244, 246, 251]);
        $pdf->text($x + 3, 49, $label, 8, false, [100, 100, 120]);
        $pdf->text($x + 3, 57, $value, 12.5, true, [30, 35, 53]);
        if ($delta !== null) {
            $pdf->text($x + 3, 62, ($delta > 0 ? '+' : '') . str_replace('.', ',', (string)$delta) . ' % vs mois précédent', 7, false, [100, 100, 120]);
        }
    }
    if ($cur['savings'] > 0) {
        $pdf->text(15, 70, 'Les tarifs négociés ont permis d\'économiser ' . money($cur['savings']) . ' (' . str_replace('.', ',', (string)$cur['savings_pct']) . ' % du prix catalogue).', 9, false, [4, 120, 87]);
    }
    // Répartitions (barres horizontales, une seule couleur)
    $y = 80;
    foreach (['centers' => 'Par centre', 'categories' => 'Par catégorie', 'suppliers' => 'Par fournisseur'] as $dim => $title) {
        $rows = array_slice($cur[$dim], 0, 6);
        if (!$rows) {
            continue;
        }
        if ($y > 245) {
            $pdf->addPage();
            $y = 20;
        }
        $pdf->text(15, $y, $title, 11, true, [30, 35, 53]);
        $y += 6;
        $max = max(array_column($rows, 'amount')) ?: 1;
        foreach ($rows as $r) {
            $pdf->text(15, $y + 3.2, mb_strimwidth($r['name'], 0, 42, '…'), 8.5, false, [30, 35, 53]);
            $w = max(0.5, 80 * $r['amount'] / $max);
            $pdf->rect(85, $y, $w, 4.2, [99, 102, 241]);
            $pdf->text(85 + $w + 2, $y + 3.2, money($r['amount']), 8.5, false, [60, 60, 80]);
            $y += 6.5;
        }
        $y += 4;
    }
    // Évolution 12 mois (colonnes)
    if ($y > 215) {
        $pdf->addPage();
        $y = 20;
    }
    $pdf->text(15, $y, 'Évolution sur 12 mois', 11, true, [30, 35, 53]);
    $y += 6;
    $months = report_monthly(null, strtotime($month . '-15'));
    $max = max(1, ...array_column($months, 'amount'));
    $baseY = $y + 42;
    foreach ($months as $i => $m) {
        $h = 38 * $m['amount'] / $max;
        $x = 18 + $i * 15;
        $pdf->rect($x, $baseY - $h, 10, max(0.3, $h), $m['month'] === $month ? [79, 70, 229] : [165, 180, 252]);
        $pdf->text($x - 0.5, $baseY + 4, month_fr($m['month'], true), 7, false, [100, 100, 120]);
    }
    $pdf->rect(15, $baseY, 182, 0.3, [200, 200, 210]);
    $pdf->text(15, $y + 1, 'max ' . money($max), 7, false, [130, 130, 150]);
    $y = $baseY + 12;
    // Budgets
    if ($d['budgets']) {
        if ($y > 240) {
            $pdf->addPage();
            $y = 20;
        }
        $pdf->text(15, $y, 'Budgets ' . substr($month, 0, 4), 11, true, [30, 35, 53]);
        $y += 6;
        foreach ($d['budgets'] as $b) {
            $pdf->text(15, $y + 3.2, mb_strimwidth($b['name'], 0, 42, '…'), 8.5, false, [30, 35, 53]);
            $pdf->rect(85, $y, 80, 4.2, [226, 232, 240]);
            $pdf->rect(85, $y, max(0.5, min(80, 80 * $b['pct'] / 100)), 4.2, $b['pct'] >= 100 ? [220, 38, 38] : ($b['pct'] >= 80 ? [217, 119, 6] : [22, 163, 74]));
            $pdf->text(168, $y + 3.2, $b['pct'] . ' % de ' . money($b['amount']), 8, false, [60, 60, 80]);
            $y += 6.5;
        }
    }
    return $pdf->output();
}

/** Génère (et conserve) le rapport du mois écoulé ; l'envoie par e-mail aux destinataires choisis. Une fois par mois. */
function report_monthly_run(bool $force = false): ?string
{
    $month = date('Y-m', strtotime('first day of last month'));
    $file = reports_dir() . '/rapport-achats-' . $month . '.pdf';
    if (is_file($file) && !$force) {
        return null;
    }
    if (!$force && !val("SELECT COUNT(*) FROM purchase_orders WHERE COALESCE(ordered_at, created_at) < ?", [date('Y-m-01')])) {
        return null; // installation récente : rien à rapporter
    }
    file_put_contents($file, report_pdf($month));
    $to = array_filter(array_map('trim', explode(',', (string)setting('report_emails', ''))), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
    if ($to && setting('mail_enabled', '0') === '1') {
        foreach ($to as $email) {
            send_mail($email, 'Rapport achats — ' . month_fr($month), '<p>Bonjour,</p><p>Veuillez trouver ci-joint le rapport des achats de ' . e(month_fr($month))
                . ' (dépenses, économies réalisées, répartition par centre, catégorie et fournisseur, budgets).</p><p>— ' . e(app_name()) . '</p>',
                [['name' => 'rapport-achats-' . $month . '.pdf', 'path' => $file, 'type' => 'application/pdf']]);
        }
    }
    return $file;
}
