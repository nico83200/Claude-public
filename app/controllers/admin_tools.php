<?php
declare(strict_types=1);

// ---------------------------------------------------------------- Budgets annuels

function admin_budgets(): void
{
    require_admin();
    $year = input_int('year', (int)date('Y'));
    if (is_post()) {
        foreach ((array)($_POST['amount'] ?? []) as $cid => $amount) {
            $cid = (int)$cid;
            $v = str_replace([' ', "\u{00A0}", '€'], '', (string)$amount);
            $v = (float)str_replace(',', '.', $v);
            $pct = max(1, min(100, (int)($_POST['alert_pct'][$cid] ?? 80)));
            $existing = one('SELECT * FROM budgets WHERE center_id = ? AND year = ?', [$cid, $year]);
            if ($existing) {
                $reset = (float)$existing['amount'] !== $v || (int)$existing['alert_pct'] !== $pct;
                update('budgets', ['amount' => $v, 'alert_pct' => $pct] + ($reset ? ['alert_sent' => 0] : []), 'center_id = ? AND year = ?', [$cid, $year]);
            } elseif ($v > 0) {
                insert('budgets', ['center_id' => $cid, 'year' => $year, 'amount' => $v, 'alert_pct' => $pct, 'alert_sent' => 0]);
            }
        }
        audit('Budgets ' . $year . ' modifiés', 'budget', null, array_map(fn($v) => (string)$v, (array)($_POST['amount'] ?? [])));
        flash('success', 'Budgets ' . $year . ' enregistrés.');
        redirect('admin/budgets', ['year' => $year]);
    }
    $centers = all('SELECT * FROM centers WHERE active = 1 ORDER BY name');
    foreach ($centers as &$c) {
        $c['budget'] = budget_status((int)$c['id'], $year);
    }
    unset($c);
    render('admin/budgets', ['title' => 'Budgets annuels', 'centers' => $centers, 'year' => $year]);
}

// ---------------------------------------------------------------- Stocks consolidés

function admin_stocks(): void
{
    require_admin();
    if (is_post()) {
        set_setting('auto_reorder', input('auto_reorder') === '1' ? '1' : '0');
        audit('Réapprovisionnement automatique ' . (input('auto_reorder') === '1' ? 'activé' : 'désactivé'), 'settings');
        flash('success', input('auto_reorder') === '1' ? 'Réapprovisionnement automatique activé : une demande est créée dès qu\'un article passe sous son seuil.' : 'Réapprovisionnement automatique désactivé.');
        redirect('admin/stocks');
    }
    $rows = all('SELECT c.id, c.name, c.color,
                   (SELECT COUNT(*) FROM stock st WHERE st.center_id = c.id) AS nb,
                   (SELECT COUNT(*) FROM stock st WHERE st.center_id = c.id AND st.alert_qty > 0 AND st.qty <= st.alert_qty) AS low,
                   (SELECT MAX(counted_at) FROM stock st WHERE st.center_id = c.id) AS last_count,
                   (SELECT COALESCE(SUM(st.qty * COALESCE(p.negotiated_price, p.catalog_price)),0) FROM stock st JOIN products p ON p.id = st.product_id WHERE st.center_id = c.id) AS value
                 FROM centers c WHERE c.active = 1 ORDER BY c.name');
    $low = all('SELECT st.*, p.name, p.unit, p.supplier_id, s.name AS supplier_name, c.name AS center_name, c.color AS center_color
                FROM stock st JOIN products p ON p.id = st.product_id JOIN suppliers s ON s.id = p.supplier_id JOIN centers c ON c.id = st.center_id
                WHERE st.alert_qty > 0 AND st.qty <= st.alert_qty ORDER BY c.name, p.name');
    $exits = all("SELECT p.name, p.unit, SUM(-m.delta) AS qty FROM stock_movements m JOIN products p ON p.id = m.product_id
                  WHERE m.type IN ('sortie','inventaire') AND m.delta < 0 AND m.created_at >= ?
                  GROUP BY p.id, p.name, p.unit ORDER BY qty DESC LIMIT 10", [date('Y-m-d H:i:s', strtotime('-90 days'))]);
    render('admin/stocks', ['title' => 'Stocks des centres', 'rows' => $rows, 'low' => $low, 'exits' => $exits]);
}

// ---------------------------------------------------------------- Mises à jour

function admin_check_password(): bool
{
    $ok = password_verify((string)($_POST['password'] ?? ''), (string)user()['password_hash']);
    if (!$ok) {
        flash('error', 'Mot de passe incorrect : opération annulée.');
    }
    return $ok;
}

function admin_updates(): void
{
    require_admin();
    $maxUpload = min(ini_bytes(ini_get('upload_max_filesize')), ini_bytes(ini_get('post_max_size')));
    render('admin/updates', [
        'title' => 'Mises à jour',
        'backups' => backups_list(),
        'history' => all('SELECT h.*, u.first_name, u.last_name FROM update_history h LEFT JOIN users u ON u.id = h.user_id ORDER BY h.created_at DESC, h.id DESC LIMIT 30'),
        'zipOk' => class_exists(ZipArchive::class),
        'writable' => is_writable(ROOT . '/app') && is_writable(ROOT . '/storage'),
        'maxUpload' => $maxUpload,
        'preview' => $_SESSION['update_preview'] ?? null,
        'remote' => licence_managed() ? (licence_check()['latest'] ?? null) : null,
        'remoteOk' => licence_updates_allowed(),
    ]);
}

function ini_bytes(string|false $v): int
{
    $v = trim((string)$v);
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) {
        'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
    };
}

/** Télécharge la version publiée par NLapps et la prépare : l'installation se confirme ensuite comme pour un paquet envoyé. */
function admin_updates_remote(): void
{
    require_admin();
    if (!is_post()) {
        redirect('admin/updates');
    }
    $staging = storage_path('update-pending.zip');
    try {
        licence_check(true);
        $l = licence_download_update($staging);
        $info = update_inspect($staging);
        $_SESSION['update_preview'] = [
            'version' => $info['version'], 'notes' => $info['notes'] ?: (string)($l['notes'] ?? ''), 'date' => $info['date'],
            'count' => count($info['files']), 'skipped' => array_slice($info['skipped'], 0, 20),
            'vendor' => $info['vendor'], 'name' => 'Téléchargée depuis ' . support_contact()['editor'],
            'newer' => version_compare($info['version'], APP_VERSION, '>'),
        ];
        flash('success', 'Version ' . $info['version'] . ' téléchargée et vérifiée : confirmez l\'installation ci-dessous.');
    } catch (Throwable $e) {
        @unlink($staging);
        flash('error', $e->getMessage());
    }
    redirect('admin/updates');
}

/** Étape 1 : envoi et analyse du paquet. Étape 2 : application après confirmation. */
function admin_updates_upload(): void
{
    require_admin();
    $staging = storage_path('update-pending.zip');
    if (input('step') === 'apply') {
        if (!admin_check_password() || !is_file($staging)) {
            redirect('admin/updates');
        }
        try {
            $r = update_apply($staging, input('allow_downgrade') === '1');
            @unlink($staging);
            unset($_SESSION['update_preview']);
            audit('Mise à jour installée', null, null, 'v' . $r['version']);
            flash('success', 'Mise à jour vers la version ' . $r['version'] . ' appliquée (' . $r['written'] . ' fichiers). Sauvegarde de la version précédente : ' . $r['backup']);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/updates');
    }
    if (input('step') === 'cancel') {
        @unlink($staging);
        unset($_SESSION['update_preview']);
        redirect('admin/updates');
    }
    $f = $_FILES['package'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        flash('error', 'Envoi du paquet impossible' . ($f && $f['error'] === UPLOAD_ERR_INI_SIZE ? ' : fichier trop volumineux pour la configuration PHP du serveur.' : '.'));
        redirect('admin/updates');
    }
    try {
        $info = update_inspect($f['tmp_name']);
        if (!move_uploaded_file($f['tmp_name'], $staging)) {
            throw new RuntimeException('Impossible de stocker le paquet dans storage/.');
        }
        $_SESSION['update_preview'] = [
            'version' => $info['version'], 'notes' => $info['notes'], 'date' => $info['date'],
            'count' => count($info['files']), 'skipped' => array_slice($info['skipped'], 0, 20),
            'vendor' => $info['vendor'], 'name' => basename((string)$f['name']),
            'newer' => version_compare($info['version'], APP_VERSION, '>'),
        ];
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates');
}

function admin_updates_rollback(): void
{
    require_admin();
    if (!admin_check_password()) {
        redirect('admin/updates');
    }
    try {
        $m = update_rollback((string)input('file'), input('restore_db') === '1');
        audit('Retour à une version antérieure', null, null, 'v' . ($m['version'] ?? '?') . (input('restore_db') === '1' ? ' (avec la base)' : ''));
        flash('success', 'Retour à la version ' . ($m['version'] ?? '?') . ' effectué' . (input('restore_db') === '1' ? ' (code et base de données).' : ' (code).')
            . ' L\'état précédent a été sauvegardé : ' . $m['safety']);
    } catch (Throwable $e) {
        flash('error', 'Retour arrière impossible : ' . $e->getMessage());
    }
    redirect('admin/updates');
}

function admin_updates_backup(): void
{
    require_admin();
    try {
        $f = backup_create('Sauvegarde manuelle', input('vendor') === '1');
        update_log('backup', APP_VERSION, APP_VERSION, $f, null);
        flash('success', 'Sauvegarde créée : ' . $f);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates');
}

function admin_updates_download(): void
{
    require_admin();
    try {
        $path = backup_path((string)input('file'));
    } catch (Throwable) {
        abort(404);
    }
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

function admin_updates_delete(): void
{
    require_admin();
    try {
        @unlink(backup_path((string)input('file')));
        flash('success', 'Sauvegarde supprimée.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/updates');
}

// ---------------------------------------------------------------- Nettoyage des données

function admin_cleanup(): void
{
    require_admin();
    if (is_post()) {
        $action = (string)input('action');
        if (!in_array($action, ['demo', 'activity'], true) || !admin_check_password()) {
            redirect('admin/cleanup');
        }
        // Sauvegarde préalable : l'opération peut être annulée depuis Mises à jour → Restaurer
        $backup = null;
        try {
            $backup = backup_create($action === 'demo' ? 'Avant suppression des données de démonstration' : 'Avant effacement de l\'activité', false);
        } catch (Throwable $e) {
            if (input('without_backup') !== '1') {
                flash('error', 'Sauvegarde préalable impossible (' . $e->getMessage() . ') : rien n\'a été supprimé. Cochez « continuer sans sauvegarde » pour forcer.');
                redirect('admin/cleanup');
            }
        }
        if ($action === 'demo') {
            $done = demo_purge();
            $parts = [];
            foreach ($done as $k => $n) {
                if ($n) {
                    $parts[] = $n . ' ' . $k;
                }
            }
            audit('Données de démonstration supprimées', 'settings', null, implode(', ', $parts) ?: 'rien à supprimer');
            flash('success', 'Données de démonstration supprimées' . ($parts ? ' : ' . implode(', ', $parts) : '') . '.'
                . ($backup ? ' Une sauvegarde a été faite au préalable (Mises à jour → Sauvegardes).' : ''));
        } else {
            activity_purge();
            audit('Activité effacée (demandes, bons, stocks, notifications)', 'settings');
            flash('success', 'Toute l\'activité a été effacée : le catalogue, les centres, les comptes et les paramètres sont conservés.'
                . ($backup ? ' Une sauvegarde a été faite au préalable.' : ''));
        }
        redirect('admin/cleanup');
    }
    render('admin/cleanup', [
        'title' => 'Nettoyage des données',
        'demo' => demo_summary(),
        'activity' => [
            'demandes' => (int)val('SELECT COUNT(*) FROM requests'),
            'bons de commande' => (int)val('SELECT COUNT(*) FROM purchase_orders'),
            'mouvements de stock' => (int)val('SELECT COUNT(*) FROM stock_movements'),
            'paniers en cours' => (int)val('SELECT COUNT(*) FROM cart_items'),
            'notifications' => (int)val('SELECT COUNT(*) FROM notifications'),
        ],
    ]);
}
