<?php
declare(strict_types=1);

/**
 * Tâches planifiées : e-mails en attente, rappels de dates limites, livraisons en retard,
 * sauvegarde quotidienne de la base et nettoyage.
 *
 * Déclenchement : tâche cron (`php cron.php` ou URL cron.php?key=…) ou, à défaut,
 * automatiquement en fin de requête (« pseudo-cron ») quand l'application est utilisée.
 */

const CRON_TASKS = [
    'mail'      => ['label' => 'Envoi des e-mails en attente',      'every' => 0],
    'deadlines' => ['label' => 'Rappels des dates limites',          'every' => 900],
    'late'      => ['label' => 'Relance des livraisons en retard',   'every' => 3600],
    'backup'    => ['label' => 'Sauvegarde quotidienne de la base',  'every' => 86400],
    'cleanup'   => ['label' => 'Nettoyage des données techniques',   'every' => 86400],
];

function cron_last(string $task): int
{
    return (int)setting('cron_last_' . $task, '0');
}

/** Exécute les tâches arrivées à échéance. Renvoie le compte rendu. */
function cron_run(bool $force = false): array
{
    $lock = ROOT . '/storage/cron.lock';
    $fh = @fopen($lock, 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
        return ['locked' => true];
    }
    @set_time_limit(300);
    $report = [];
    try {
        foreach (CRON_TASKS as $task => $def) {
            if (!$force && $def['every'] > 0 && time() - cron_last($task) < $def['every']) {
                continue;
            }
            try {
                $report[$task] = match ($task) {
                    'mail' => mail_queue_process(30),
                    'deadlines' => cron_deadline_reminders(),
                    'late' => cron_late_deliveries(),
                    'backup' => cron_daily_backup($force),
                    'cleanup' => cron_cleanup(),
                };
                set_setting('cron_last_' . $task, (string)time());
            } catch (Throwable $e) {
                $report[$task] = 'Erreur : ' . $e->getMessage();
                error_log('[cron ' . $task . '] ' . $e->getMessage());
            }
        }
        set_setting('cron_last_run', (string)time());
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
    return $report;
}

/** Pseudo-cron : en fin de requête, après envoi de la page à l'utilisateur. */
function cron_after_request(): void
{
    if (setting('pseudo_cron', '1') !== '1' && empty($GLOBALS['mail_pending'])) {
        return;
    }
    $due = !empty($GLOBALS['mail_pending']) || time() - (int)setting('cron_last_run', '0') >= 120;
    if (!$due) {
        return;
    }
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ignore_user_abort(true);
        try {
            if (setting('pseudo_cron', '1') === '1') {
                cron_run();
            } else {
                mail_queue_process(10);
            }
        } catch (Throwable $e) {
            error_log('[pseudo-cron] ' . $e->getMessage());
        }
    });
}

/** La veille (24 h avant) d'une date limite, rappel aux salariés des centres concernés. */
function cron_deadline_reminders(): int
{
    $n = 0;
    $rows = all('SELECT d.*, s.name AS supplier_name FROM deadlines d LEFT JOIN suppliers s ON s.id = d.supplier_id
                 WHERE d.reminded_at IS NULL AND d.deadline_at > ? AND d.deadline_at <= ?', [now(), date('Y-m-d H:i:s', time() + 86400)]);
    foreach ($rows as $d) {
        $users = $d['center_id']
            ? array_column(all("SELECT u.id FROM users u JOIN user_centers uc ON uc.user_id = u.id WHERE uc.center_id = ? AND u.status = 'active'", [$d['center_id']]), 'id')
            : array_column(all("SELECT DISTINCT u.id FROM users u JOIN user_centers uc ON uc.user_id = u.id WHERE u.status = 'active'"), 'id');
        notify($users, 'deadline_reminder', 'Rappel : ' . $d['title'] . ' — ' . date_fr($d['deadline_at'], true),
            'Dernier délai pour vos demandes' . ($d['supplier_name'] ? ' ' . $d['supplier_name'] : '') . ' : ' . date_long_fr($d['deadline_at']) . ' à ' . date('H\hi', strtotime($d['deadline_at'])) . '.'
                . ($d['description'] ? "\n" . $d['description'] : ''), url('catalog'));
        update('deadlines', ['reminded_at' => now()], 'id = ?', [$d['id']]);
        $n++;
    }
    return $n;
}

/** Livraisons non réceptionnées au-delà du délai paramétré (ou de la date prévue). */
function cron_late_deliveries(): int
{
    $days = max(1, (int)setting('late_days', '10'));
    $rows = all("SELECT po.*, s.name AS supplier_name, c.name AS center_name FROM purchase_orders po
                 JOIN suppliers s ON s.id = po.supplier_id JOIN centers c ON c.id = po.center_id
                 WHERE po.status IN ('commande','partiel') AND po.late_notified_at IS NULL
                   AND ((po.expected_date IS NOT NULL AND po.expected_date < ?) OR (po.expected_date IS NULL AND po.ordered_at < ?))",
        [date('Y-m-d'), date('Y-m-d H:i:s', strtotime("-$days days"))]);
    foreach ($rows as $po) {
        $users = array_column(all("SELECT u.id FROM users u JOIN user_centers uc ON uc.user_id = u.id WHERE uc.center_id = ? AND u.status = 'active'", [$po['center_id']]), 'id');
        $t = 'Livraison en retard : ' . $po['supplier_name'] . ' (' . $po['center_name'] . ')';
        $b = 'Le bon ' . $po['po_number'] . ', commandé le ' . date_fr($po['ordered_at']) . ', n\'est pas entièrement réceptionné. Si la marchandise est arrivée, pensez à la réceptionner ; sinon, le service achats va relancer le fournisseur.';
        notify($users, 'delivery_late', $t, $b, url('reception', ['id' => $po['id']]));
        notify(admin_ids(), 'delivery_late', $t, $b, url('admin/order', ['id' => $po['id']]));
        update('purchase_orders', ['late_notified_at' => now()], 'id = ?', [$po['id']]);
    }
    return count($rows);
}

function daily_backups_dir(): string
{
    $d = backups_dir() . '/daily';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** Sauvegarde de la base (un fichier par jour) et purge selon la durée de conservation. */
function cron_daily_backup(bool $force = false): string
{
    $file = daily_backups_dir() . '/base-' . date('Y-m-d') . '.zip';
    if (!$force && is_file($file)) {
        return 'déjà faite';
    }
    backup_db_only($file, 'Sauvegarde quotidienne');
    $keep = max(3, (int)setting('backup_keep_days', '30'));
    foreach (glob(daily_backups_dir() . '/base-*.zip') ?: [] as $f) {
        if (filemtime($f) < time() - $keep * 86400) {
            @unlink($f);
        }
    }
    return basename($file);
}

function backup_db_only(string $path, string $reason): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer la sauvegarde de la base.');
    }
    $dump = tempnam(sys_get_temp_dir(), 'dbdump');
    db_dump_to($dump);
    $zip->addFile($dump, '__database.jsonl');
    $zip->addFromString('__meta.json', json_encode(['version' => APP_VERSION, 'created_at' => now(), 'reason' => $reason, 'driver' => db_driver(), 'db_only' => true], JSON_UNESCAPED_UNICODE));
    $zip->close();
    @unlink($dump);
}

function daily_backups_list(): array
{
    $out = [];
    foreach (glob(daily_backups_dir() . '/base-*.zip') ?: [] as $f) {
        $out[] = ['file' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

function cron_cleanup(): int
{
    $n = q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', strtotime('-30 days'))])->rowCount();
    $n += q('DELETE FROM password_resets WHERE expires_at < ?', [date('Y-m-d H:i:s', strtotime('-7 days'))])->rowCount();
    $n += q('DELETE FROM mail_queue WHERE sent_at IS NOT NULL AND sent_at < ?', [date('Y-m-d H:i:s', strtotime('-30 days'))])->rowCount();
    $n += q('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < ?', [date('Y-m-d H:i:s', strtotime('-12 months'))])->rowCount();
    $n += q('DELETE FROM ai_cache WHERE created_at < ?', [date('Y-m-d H:i:s', strtotime('-30 days'))])->rowCount();
    // Pièces jointes d'e-mails déjà envoyés
    foreach (glob(ROOT . '/storage/mail/*') ?: [] as $f) {
        if (filemtime($f) < time() - 30 * 86400) {
            @unlink($f);
        }
    }
    return $n;
}
