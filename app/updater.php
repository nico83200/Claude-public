<?php
declare(strict_types=1);

/**
 * Mises à jour du progiciel depuis l'interface d'administration.
 *
 * Paquet de mise à jour : archive .zip contenant un fichier version.json
 * ({"version": "1.2.0", "notes": "..."}) et les fichiers du code.
 * Avant chaque mise à jour, une sauvegarde complète (code + base) est créée
 * dans storage/backups/ : elle permet de revenir à la version antérieure.
 */

/** Chemins que le paquet peut remplacer (fichier exact ou dossier se terminant par /). */
const UPDATE_ALLOWED = ['index.php', 'VERSION', 'CHANGELOG.md', 'README.md', 'composer.json', 'composer.lock', '.htaccess', 'config.sample.php', 'app/', 'assets/', 'vendor/', 'tools/'];

function backups_dir(): string
{
    $d = ROOT . '/storage/backups';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

function update_path_allowed(string $rel): bool
{
    if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0") || str_starts_with($rel, '/') || preg_match('#^[a-zA-Z]:#', $rel) || str_contains($rel, '\\')) {
        return false;
    }
    foreach (UPDATE_ALLOWED as $a) {
        if ($rel === $a || (str_ends_with($a, '/') && str_starts_with($rel, $a))) {
            return true;
        }
    }
    return false;
}

/** Analyse un paquet : version, notes, fichiers installables et fichiers ignorés. */
function update_inspect(string $zipPath): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('L\'extension PHP « zip » n\'est pas disponible sur le serveur.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Archive ZIP illisible.');
    }
    // Le paquet peut contenir un dossier racine unique (ex : commandes-centres-1.2.0/)
    $prefix = '';
    $manifestIdx = $zip->locateName('version.json');
    if ($manifestIdx === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string)$zip->getNameIndex($i);
            if (preg_match('#^([^/]+/)version\.json$#', $n, $m)) {
                $prefix = $m[1];
                $manifestIdx = $i;
                break;
            }
        }
    }
    if ($manifestIdx === false) {
        $zip->close();
        throw new RuntimeException('Paquet invalide : fichier version.json absent.');
    }
    $manifest = json_decode((string)$zip->getFromIndex($manifestIdx), true);
    if (!is_array($manifest) || empty($manifest['version']) || !preg_match('/^\d+\.\d+\.\d+([.-][\w.]+)?$/', (string)$manifest['version'])) {
        $zip->close();
        throw new RuntimeException('Paquet invalide : version.json mal formé.');
    }
    $files = [];
    $skipped = [];
    $hasVendor = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = (string)$zip->getNameIndex($i);
        if (str_ends_with($n, '/') || ($prefix !== '' && !str_starts_with($n, $prefix))) {
            continue;
        }
        $rel = substr($n, strlen($prefix));
        if ($rel === 'version.json') {
            continue;
        }
        if (update_path_allowed($rel)) {
            $files[$i] = $rel;
            $hasVendor = $hasVendor || str_starts_with($rel, 'vendor/');
        } else {
            $skipped[] = $rel;
        }
    }
    $zip->close();
    if (!$files) {
        throw new RuntimeException('Le paquet ne contient aucun fichier applicable.');
    }
    if (!in_array('VERSION', $files, true)) {
        throw new RuntimeException('Paquet invalide : fichier VERSION absent.');
    }
    return [
        'version' => (string)$manifest['version'], 'notes' => (string)($manifest['notes'] ?? ''),
        'date' => (string)($manifest['date'] ?? ''), 'files' => $files, 'skipped' => $skipped, 'vendor' => $hasVendor,
    ];
}

/** Liste récursive des fichiers de code actuellement installés. */
function code_files(bool $withVendor): array
{
    $out = [];
    foreach (UPDATE_ALLOWED as $a) {
        if ($a === 'vendor/' && !$withVendor) {
            continue;
        }
        $path = ROOT . '/' . rtrim($a, '/');
        if (is_file($path)) {
            $out[] = $a;
        } elseif (is_dir($path)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $out[] = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(ROOT))), '/');
                }
            }
        }
    }
    return $out;
}

// ---------------------------------------------------------------- Sauvegarde / restauration de la base

function db_tables(): array
{
    $tables = [];
    foreach (schema_statements(db_driver()) as $s) {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS (\w+)/', $s, $m)) {
            $tables[] = $m[1];
        }
    }
    return $tables;
}

/** Export de toutes les tables au format JSON (une ligne par table et par bloc). */
function db_dump_to(string $file): void
{
    $fh = fopen($file, 'w');
    foreach (db_tables() as $t) {
        try {
            $st = db()->query("SELECT * FROM $t");
        } catch (Throwable) {
            continue;
        }
        fwrite($fh, json_encode(['table' => $t, 'rows' => []]) . "\n");
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = $row;
            if (count($batch) >= 500) {
                fwrite($fh, json_encode(['table' => $t, 'rows' => $batch], JSON_UNESCAPED_UNICODE) . "\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($fh, json_encode(['table' => $t, 'rows' => $batch], JSON_UNESCAPED_UNICODE) . "\n");
        }
    }
    fclose($fh);
}

function db_restore_from(string $file): void
{
    $pdo = db();
    $sqlite = db_driver() === 'sqlite';
    $pdo->exec($sqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS = 0');
    $pdo->beginTransaction();
    try {
        $cleared = [];
        $cols = [];
        $fh = fopen($file, 'r');
        while (($line = fgets($fh)) !== false) {
            $chunk = json_decode($line, true);
            if (!is_array($chunk) || empty($chunk['table']) || !preg_match('/^\w+$/', $chunk['table'])) {
                continue;
            }
            $t = $chunk['table'];
            if ($t === 'update_history') {
                continue; // l'historique des mises à jour est conservé tel quel
            }
            if (!isset($cleared[$t])) {
                $pdo->exec("DELETE FROM $t");
                $cleared[$t] = true;
                $cols[$t] = array_flip(array_map(fn($c) => $sqlite ? $c['name'] : $c['Field'],
                    all($sqlite ? "PRAGMA table_info($t)" : "SHOW COLUMNS FROM $t")));
            }
            foreach ($chunk['rows'] as $row) {
                $row = array_intersect_key($row, $cols[$t]);
                if ($row) {
                    insert($t, $row);
                }
            }
        }
        fclose($fh);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->exec($sqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
    }
}

// ---------------------------------------------------------------- Sauvegardes

/**
 * Crée une sauvegarde : code (vendor inclus si demandé), base de données,
 * et liste des fichiers qu'une mise à jour va ajouter (supprimés en cas de retour arrière).
 */
function backup_create(string $reason, bool $withVendor, array $addedFiles = [], ?string $targetVersion = null): string
{
    @set_time_limit(300);
    $name = 'v' . APP_VERSION . '_' . date('Ymd-His') . '_' . bin2hex(random_bytes(3)) . '.zip';
    $path = backups_dir() . '/' . $name;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer la sauvegarde (droits d\'écriture sur storage/ ?).');
    }
    foreach (code_files($withVendor) as $rel) {
        $zip->addFile(ROOT . '/' . $rel, 'files/' . $rel);
    }
    $dump = tempnam(sys_get_temp_dir(), 'dbdump');
    db_dump_to($dump);
    $zip->addFile($dump, '__database.jsonl');
    $zip->addFromString('__added.json', json_encode(array_values($addedFiles)));
    $zip->addFromString('__meta.json', json_encode([
        'version' => APP_VERSION, 'created_at' => now(), 'reason' => $reason, 'vendor' => $withVendor,
        'target_version' => $targetVersion, 'by' => trim((user()['first_name'] ?? '') . ' ' . (user()['last_name'] ?? '')),
        'driver' => db_driver(),
    ], JSON_UNESCAPED_UNICODE));
    $zip->close();
    @unlink($dump);
    return $name;
}

function backups_list(): array
{
    $out = [];
    foreach (glob(backups_dir() . '/*.zip') ?: [] as $f) {
        $zip = new ZipArchive();
        $meta = [];
        if ($zip->open($f) === true) {
            $meta = json_decode((string)$zip->getFromName('__meta.json'), true) ?: [];
            $zip->close();
        }
        $out[] = $meta + ['file' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

function backup_path(string $file): string
{
    if (!preg_match('/^[\w.-]+\.zip$/', $file) || !is_file(backups_dir() . '/' . $file)) {
        throw new RuntimeException('Sauvegarde introuvable.');
    }
    return backups_dir() . '/' . $file;
}

function update_log(string $action, ?string $from, ?string $to, ?string $backup, ?string $notes): void
{
    try {
        insert('update_history', [
            'action' => $action, 'from_version' => $from, 'to_version' => $to, 'backup_file' => $backup,
            'notes' => $notes, 'user_id' => user()['id'] ?? null, 'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        error_log('[update] ' . $e->getMessage());
    }
}

function maintenance(bool $on): void
{
    $f = ROOT . '/storage/maintenance.flag';
    $on ? @file_put_contents($f, (string)time()) : @unlink($f);
}

/** Écrit un fichier de façon atomique (fichier temporaire puis renommage). */
function write_file_atomic(string $rel, string $content): void
{
    $dest = ROOT . '/' . $rel;
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Impossible de créer le dossier ' . $rel);
    }
    $tmp = $dest . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $content) === false || !@rename($tmp, $dest)) {
        @unlink($tmp);
        throw new RuntimeException('Impossible d\'écrire ' . $rel . ' (droits d\'écriture ?).');
    }
}

function update_apply(string $zipPath, bool $allowDowngrade = false): array
{
    @set_time_limit(300);
    $info = update_inspect($zipPath);
    if (!$allowDowngrade && version_compare($info['version'], APP_VERSION, '<=')) {
        throw new RuntimeException('La version ' . $info['version'] . ' n\'est pas plus récente que la version installée (' . APP_VERSION . ').');
    }
    $added = array_values(array_filter($info['files'], fn($rel) => !file_exists(ROOT . '/' . $rel)));
    $backup = backup_create('Avant mise à jour vers ' . $info['version'], $info['vendor'], $added, $info['version']);

    maintenance(true);
    $zip = new ZipArchive();
    $zip->open($zipPath);
    $written = 0;
    try {
        // VERSION en dernier : si l'opération échoue, la version annoncée reste l'ancienne
        $ordered = $info['files'];
        uasort($ordered, fn($a, $b) => ($a === 'VERSION') <=> ($b === 'VERSION'));
        foreach ($ordered as $idx => $rel) {
            $content = $zip->getFromIndex((int)$idx);
            if ($content === false) {
                throw new RuntimeException('Lecture impossible : ' . $rel);
            }
            write_file_atomic($rel, $content);
            $written++;
        }
    } catch (Throwable $e) {
        $zip->close();
        // Remise en état automatique depuis la sauvegarde
        try {
            backup_restore_files(backups_dir() . '/' . $backup);
        } catch (Throwable) {
        }
        maintenance(false);
        throw new RuntimeException('Échec de la mise à jour, l\'ancienne version a été restaurée : ' . $e->getMessage());
    }
    $zip->close();
    maintenance(false);
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    update_log('update', APP_VERSION, $info['version'], $backup, $info['notes'] ?: null);
    return $info + ['backup' => $backup, 'written' => $written];
}

/** Restaure les fichiers d'une sauvegarde et retire ceux ajoutés depuis. */
function backup_restore_files(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Sauvegarde illisible.');
    }
    $meta = json_decode((string)$zip->getFromName('__meta.json'), true) ?: [];
    $added = json_decode((string)$zip->getFromName('__added.json'), true) ?: [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = (string)$zip->getNameIndex($i);
        if (!str_starts_with($n, 'files/') || str_ends_with($n, '/')) {
            continue;
        }
        $rel = substr($n, 6);
        if (update_path_allowed($rel)) {
            write_file_atomic($rel, (string)$zip->getFromIndex($i));
        }
    }
    foreach ($added as $rel) {
        if (is_string($rel) && update_path_allowed($rel) && is_file(ROOT . '/' . $rel)) {
            @unlink(ROOT . '/' . $rel);
        }
    }
    $zip->close();
    return $meta;
}

/** Retour à une version antérieure, avec restauration optionnelle de la base. */
function update_rollback(string $file, bool $restoreDb): array
{
    @set_time_limit(300);
    $path = backup_path($file);
    $zip = new ZipArchive();
    $zip->open($path);
    $meta = json_decode((string)$zip->getFromName('__meta.json'), true) ?: [];
    $zip->close();
    if ($restoreDb && ($meta['driver'] ?? db_driver()) !== db_driver()) {
        throw new RuntimeException('La sauvegarde provient d\'un autre type de base de données.');
    }
    // Sauvegarde de l'état actuel : le retour arrière est lui-même réversible
    $safety = backup_create('Avant retour à la version ' . ($meta['version'] ?? '?'), !empty($meta['vendor']), [], $meta['version'] ?? null);
    $from = APP_VERSION;
    maintenance(true);
    try {
        backup_restore_files($path);
        if ($restoreDb) {
            $tmp = tempnam(sys_get_temp_dir(), 'dbrest');
            $zip->open($path);
            file_put_contents($tmp, (string)$zip->getFromName('__database.jsonl'));
            $zip->close();
            db_restore_from($tmp);
            @unlink($tmp);
        }
    } finally {
        maintenance(false);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    set_setting('db_version', (string)($meta['version'] ?? ''));
    update_log('rollback', $from, $meta['version'] ?? null, $safety, $restoreDb ? 'Base de données restaurée' : 'Code seul');
    return $meta + ['safety' => $safety];
}
