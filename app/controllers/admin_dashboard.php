<?php
declare(strict_types=1);

function admin_dashboard(): void
{
    require_admin();
    $monthStart = date('Y-m-01 00:00:00');
    $yearStart = date('Y-01-01 00:00:00');

    $kpi = [
        'pending_lines'   => (int)val("SELECT COUNT(*) FROM request_lines WHERE status = 'pending'"),
        'urgent'          => (int)val("SELECT COUNT(*) FROM request_lines rl JOIN requests r ON r.id = rl.request_id WHERE rl.status = 'pending' AND r.urgent = 1"),
        'to_order'        => (int)val("SELECT COUNT(*) FROM purchase_orders WHERE status = 'a_commander'"),
        'ordered'         => (int)val("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('commande','partiel')"),
        'pending_users'   => (int)val("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
        'month_spend'     => (float)val("SELECT COALESCE(SUM(l.qty*l.unit_price),0) FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                                         WHERE po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?", [$monthStart]),
        'year_spend'      => (float)val("SELECT COALESCE(SUM(l.qty*l.unit_price),0) FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                                         WHERE po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?", [$yearStart]),
        'year_savings'    => (float)val("SELECT COALESCE(SUM(l.qty*(l.catalog_price - l.unit_price)),0) FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                                         WHERE po.status IN ('commande','partiel','recu') AND po.ordered_at >= ? AND l.catalog_price > l.unit_price", [$yearStart]),
    ];

    $groups = pending_groups();
    usort($groups, fn($a, $b) => [$b['urgent'], $a['oldest']] <=> [$a['urgent'], $b['oldest']]);

    $byCenter = all("SELECT c.id, c.name, c.color, COALESCE(SUM(l.qty*l.unit_price),0) total
                     FROM centers c
                     LEFT JOIN purchase_orders po ON po.center_id = c.id AND po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?
                     LEFT JOIN purchase_order_lines l ON l.purchase_order_id = po.id
                     WHERE c.active = 1 GROUP BY c.id, c.name, c.color ORDER BY total DESC", [$yearStart]);
    $bySupplier = all("SELECT s.name, s.color, SUM(l.qty*l.unit_price) total
                       FROM purchase_orders po JOIN purchase_order_lines l ON l.purchase_order_id = po.id JOIN suppliers s ON s.id = po.supplier_id
                       WHERE po.status IN ('commande','partiel','recu') AND po.ordered_at >= ?
                       GROUP BY s.id, s.name, s.color ORDER BY total DESC LIMIT 6", [$yearStart]);
    $lateOrders = all("SELECT po.*, s.name AS supplier_name, c.name AS center_name FROM purchase_orders po
                       JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id
                       WHERE po.status IN ('commande','partiel') AND po.ordered_at < ? ORDER BY po.ordered_at LIMIT 6",
                       [date('Y-m-d H:i:s', strtotime('-10 days'))]);
    $toOrder = all("SELECT po.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS center_name FROM purchase_orders po
                    JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id
                    WHERE po.status = 'a_commander' ORDER BY po.created_at LIMIT 8");
    foreach ($toOrder as &$po) {
        $po['totals'] = po_totals((int)$po['id']);
    }
    unset($po);

    render('admin/dashboard', [
        'title' => 'Tableau de bord achats',
        'kpi' => $kpi,
        'groups' => array_slice($groups, 0, 8),
        'monthly' => monthly_spend(null, 12),
        'byCenter' => $byCenter,
        'bySupplier' => $bySupplier,
        'deadlines' => upcoming_deadlines(null, 6),
        'lateOrders' => $lateOrders,
        'toOrder' => $toOrder,
    ]);
}

/** Tableau de bord de direction : dépenses, économies, répartitions, évolution, budgets. */
function admin_direction(): void
{
    require_admin();
    if (is_post()) {
        $emails = implode(', ', array_filter(array_map('trim', preg_split('/[,;\s]+/', (string)input('report_emails', ''))), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        set_setting('report_emails', $emails ?: null);
        flash('success', $emails ? 'Le rapport mensuel sera envoyé à : ' . $emails . '.' : 'Envoi automatique du rapport désactivé (il reste téléchargeable ici).');
        redirect('admin/direction');
    }
    $period = isset(REPORT_PERIODS[(string)input('period')]) ? (string)input('period') : 'month';
    $center = input_int('center') ?: null;
    $reports = array_map(fn($f) => ['file' => basename($f), 'month' => substr(basename($f, '.pdf'), -7)], glob(reports_dir() . '/rapport-achats-*.pdf') ?: []);
    usort($reports, fn($a, $b) => strcmp($b['month'], $a['month']));
    render('admin/direction', [
        'title' => 'Tableau de bord direction', 'period' => $period, 'center' => $center, 'd' => report_data($period, $center),
        'centers' => all('SELECT id, name FROM centers WHERE active = 1 ORDER BY name'), 'reports' => $reports,
    ]);
}

/** Rapport PDF d'un mois (généré à la demande, ou celui conservé). */
function admin_direction_pdf(): void
{
    require_admin();
    $month = preg_match('/^\d{4}-\d{2}$/', (string)input('month')) ? (string)input('month') : date('Y-m', strtotime('first day of last month'));
    $file = reports_dir() . '/rapport-achats-' . $month . '.pdf';
    $pdf = is_file($file) && $month < date('Y-m') ? (string)file_get_contents($file) : report_pdf($month);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="rapport-achats-' . $month . '.pdf"');
    echo $pdf;
    exit;
}

/** Fiche RGPD et sécurité, prête à remettre à la direction ou au DPO (imprimable / PDF). */
function admin_rgpd(): void
{
    require_admin();
    if (is_post()) {
        foreach (['rgpd_controller', 'rgpd_dpo', 'rgpd_hosting'] as $k) {
            set_setting($k, mb_substr(trim((string)input($k, '')), 0, 300) ?: null);
        }
        flash('success', 'Informations de la fiche enregistrées.');
        redirect('admin/rgpd');
    }
    render('admin/rgpd', [
        'title' => 'Fiche RGPD et sécurité',
        'stats' => ['users' => (int)val("SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL"), 'centers' => (int)val('SELECT COUNT(*) FROM centers WHERE active = 1')],
        'ai' => setting('ai_enabled', '1') === '1' && ai_api_key() !== '', 'mail' => setting('mail_enabled', '0') === '1',
        'nlapps' => licence_managed(), 'backupDays' => max(3, (int)setting('backup_keep_days', '30')),
        'admins2fa' => [(int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND totp_secret IS NOT NULL"), (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")],
    ], null);
}

