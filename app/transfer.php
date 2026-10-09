<?php
declare(strict_types=1);

/**
 * Export / import complet d'un Centriva vers un autre : base de données entière (centres, comptes, catalogue,
 * fournisseurs, demandes, commandes, stock, contrats, budgets, réglages…) et fichiers (photos, logo, factures, contrats).
 *
 * L'archive est un .zip chiffré (AES-256) par un mot de passe choisi à l'export : elle contient des données
 * personnelles et les secrets de l'installation. Les valeurs chiffrées avec la clé de l'installation d'origine
 * (double authentification, mot de passe SMTP, clé IA) y sont transportées en clair, à l'abri du chiffrement de l'archive,
 * puis re-chiffrées avec la clé de l'installation de destination à l'import.
 *
 * Restent propres à chaque installation (jamais exportés ni écrasés) : licence et accès au centre d'assistance NLapps,
 * clé des tâches planifiées, historique des mises à jour, tutoriels vidéo, file d'e-mails en attente.
 */

const TRANSFER_FORMAT = 'centriva-export';
const TRANSFER_FORMAT_VERSION = 1;

/** Tables jamais exportées (propres à l'installation ou purement techniques). */
const TRANSFER_SKIP_TABLES = ['update_history', 'videos', 'mail_queue', 'login_attempts', 'ai_cache', 'password_resets'];

/** Réglages propres à l'installation de destination : ni exportés, ni remplacés. */
function transfer_local_setting(string $key): bool
{
    return in_array($key, ['db_version', 'cron_key', 'support_hub_url', 'support_hub_key', 'app_url', 'faq_remote', 'faq_remote_hash',
        'videos_remote_hash', 'welcome_video', 'renamed_centriva', 'demo_reset_at', 'demo_ai_count', 'onboarding_hidden'], true)
        || (bool)preg_match('/^(cron_last_|licence_|support_)/', $key);
}

/** Dossiers de fichiers transférés : [chemin dans l'archive => dossier local]. */
function transfer_dirs(): array
{
    return [
        'files/uploads/products' => uploads_path('products'),
        'files/uploads/brand' => uploads_path('brand'),
        'files/storage/invoices' => storage_path('invoices'),
        'files/storage/contracts' => storage_path('contracts'),
    ];
}

/** Chiffres clés de la base courante (aperçu avant import, manifeste de l'export). */
function transfer_counts(): array
{
    $n = fn(string $t, string $w = '') => (function () use ($t, $w) {
        try {
            return (int)val("SELECT COUNT(*) FROM $t" . ($w ? " WHERE $w" : ''));
        } catch (Throwable) {
            return 0;
        }
    })();
    return [
        'centres' => $n('centers'), 'comptes' => $n('users', 'deleted_at IS NULL'), 'fournisseurs' => $n('suppliers'),
        'articles' => $n('products'), 'demandes' => $n('requests'), 'bons de commande' => $n('purchase_orders'),
        'articles en stock' => $n('stock'), 'contrats' => $n('contracts'),
    ];
}

/** Remplace les valeurs chiffrées avec la clé locale par leur valeur en clair, marquée pour être re-chiffrée à l'import. */
function transfer_unseal(array $row): array
{
    foreach ($row as $k => $v) {
        if (is_string($v) && preg_match('/^enc2?:/', $v)) {
            $plain = decrypt_secret($v);
            $row[$k] = $plain === '' ? null : 'plain:' . base64_encode($plain);
        }
    }
    return $row;
}

function transfer_seal(array $row): array
{
    foreach ($row as $k => $v) {
        if (is_string($v) && str_starts_with($v, 'plain:')) {
            $row[$k] = encrypt_secret((string)base64_decode(substr($v, 6)));
        }
    }
    return $row;
}

/**
 * Crée l'archive d'export (fichier temporaire) et renvoie son chemin.
 * $password : 10 caractères minimum, indispensable à l'import.
 */
function transfer_export(string $password, bool $withFiles): string
{
    if (mb_strlen($password) < 10) {
        throw new RuntimeException('Mot de passe de l\'archive : 10 caractères minimum.');
    }
    @set_time_limit(600);
    $tmpDb = tempnam(sys_get_temp_dir(), 'cexp');
    $fh = fopen($tmpDb, 'w');
    foreach (db_tables() as $t) {
        if (in_array($t, TRANSFER_SKIP_TABLES, true)) {
            continue;
        }
        try {
            $st = db()->query("SELECT * FROM $t");
        } catch (Throwable) {
            continue;
        }
        fwrite($fh, json_encode(['table' => $t, 'rows' => []]) . "\n");
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($t === 'settings' && transfer_local_setting((string)$row['skey'])) {
                continue;
            }
            $batch[] = transfer_unseal($row);
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

    $path = storage_path('imports');
    @mkdir($path, 0750, true);
    $path .= '/export-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Impossible de créer l\'archive (droits d\'écriture du dossier storage/ ?).');
    }
    $zip->setPassword($password);
    $files = 0;
    $add = function (string $local, string $name) use ($zip, &$files) {
        $zip->addFile($local, $name);
        $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
        $files++;
    };
    $add($tmpDb, 'database.jsonl');
    if ($withFiles) {
        foreach (transfer_dirs() as $prefix => $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f) && basename($f) !== '.htaccess') {
                    $add($f, $prefix . '/' . basename($f));
                }
            }
        }
    }
    $manifest = [
        'format' => TRANSFER_FORMAT, 'format_version' => TRANSFER_FORMAT_VERSION, 'app_version' => APP_VERSION,
        'exported_at' => now(), 'name' => (string)(setting('company_name') ?: app_name()), 'driver' => db_driver(),
        'counts' => transfer_counts(), 'files' => $withFiles ? $files - 1 : 0,
    ];
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
    $zip->close();
    @unlink($tmpDb);
    return $path;
}

/** Ouvre une archive d'export et vérifie son mot de passe et sa version. Renvoie le manifeste. */
function transfer_inspect(string $path, string $password): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Fichier illisible : choisissez l\'archive .zip produite par « Exporter » dans un autre Centriva.');
    }
    $zip->setPassword($password);
    $raw = $zip->getFromName('manifest.json');
    $hasDb = $zip->locateName('database.jsonl') !== false;
    $zip->close();
    if ($raw === false) {
        throw new RuntimeException($hasDb ? 'Mot de passe de l\'archive incorrect.' : 'Ce fichier n\'est pas un export Centriva.');
    }
    $m = json_decode($raw, true);
    if (!is_array($m) || ($m['format'] ?? '') !== TRANSFER_FORMAT || !$hasDb) {
        throw new RuntimeException('Ce fichier n\'est pas un export Centriva.');
    }
    if ((int)($m['format_version'] ?? 0) > TRANSFER_FORMAT_VERSION || version_compare((string)$m['app_version'], APP_VERSION, '>')) {
        throw new RuntimeException('Cet export vient de Centriva ' . $m['app_version'] . ', plus récent que cette installation (' . APP_VERSION . ') : mettez d\'abord celle-ci à jour.');
    }
    return $m;
}

/**
 * Remplace toutes les données de cette installation par celles de l'archive.
 * Une sauvegarde de la base actuelle est faite avant (storage/backups/avant-import-….jsonl), et sert à revenir en arrière en cas d'échec.
 */
function transfer_import(string $path, string $password): array
{
    $m = transfer_inspect($path, $password);
    @set_time_limit(900);
    $zip = new ZipArchive();
    $zip->open($path);
    $zip->setPassword($password);
    $tmpDb = tempnam(sys_get_temp_dir(), 'cimp');
    $in = $zip->getStream('database.jsonl');
    if (!$in) {
        throw new RuntimeException('Base de données de l\'archive illisible.');
    }
    $out = fopen($tmpDb, 'w');
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);

    @mkdir(storage_path('backups'), 0750, true);
    $backup = storage_path('backups/avant-import-' . date('Ymd-His') . '.jsonl');
    db_dump_to($backup);

    $local = all('SELECT skey, svalue FROM settings');
    $pdo = db();
    $sqlite = db_driver() === 'sqlite';
    $stats = ['lignes' => 0, 'fichiers' => 0];
    $pdo->exec($sqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS = 0');
    $pdo->beginTransaction();
    try {
        // 1. Toutes les données de cette installation sont effacées (sauf ce qui lui est propre)
        foreach (db_tables() as $t) {
            if (!in_array($t, ['update_history', 'videos'], true)) {
                $pdo->exec("DELETE FROM $t");
            }
        }
        // 2. Données de l'archive (colonnes inconnues ignorées : export d'une version plus ancienne ou plus récente)
        $cols = [];
        $known = array_flip(db_tables());
        $fh = fopen($tmpDb, 'r');
        while (($line = fgets($fh)) !== false) {
            $chunk = json_decode($line, true);
            $t = (string)($chunk['table'] ?? '');
            if (!preg_match('/^\w+$/', $t) || !isset($known[$t]) || in_array($t, TRANSFER_SKIP_TABLES, true)) {
                continue;
            }
            $cols[$t] ??= array_flip(array_map(fn($c) => $sqlite ? $c['name'] : $c['Field'], all($sqlite ? "PRAGMA table_info($t)" : "SHOW COLUMNS FROM $t")));
            foreach ((array)$chunk['rows'] as $row) {
                if ($t === 'settings' && transfer_local_setting((string)($row['skey'] ?? ''))) {
                    continue;
                }
                $row = transfer_seal(array_intersect_key((array)$row, $cols[$t]));
                if ($row) {
                    insert($t, $row);
                    $stats['lignes']++;
                }
            }
        }
        fclose($fh);
        // 3. Réglages propres à cette installation : rétablis
        foreach ($local as $s) {
            if (transfer_local_setting((string)$s['skey'])) {
                q('DELETE FROM settings WHERE skey = ?', [$s['skey']]);
                insert('settings', $s);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $zip->close();
        @unlink($tmpDb);
        throw new RuntimeException('Import interrompu, rien n\'a été modifié : ' . $e->getMessage());
    } finally {
        $pdo->exec($sqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
    }
    @unlink($tmpDb);
    setting('', null, true);

    // 4. Fichiers : ceux de l'archive remplacent ceux de cette installation
    if ((int)($m['files'] ?? 0) > 0) {
        foreach (transfer_dirs() as $prefix => $dir) {
            @mkdir($dir, 0755, true);
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f) && basename($f) !== '.htaccess') {
                    @unlink($f);
                }
            }
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            foreach (transfer_dirs() as $prefix => $dir) {
                $base = basename($name);
                if (str_starts_with($name, $prefix . '/') && $base !== '.htaccess' && preg_match('/^[\w.-]+$/', $base) && !preg_match('/\.(php\d?|phtml|phar|pl|py|cgi|sh|htaccess)$/i', $base)) {
                    file_put_contents($dir . '/' . $base, (string)$zip->getFromIndex($i));
                    $stats['fichiers']++;
                }
            }
        }
    }
    $zip->close();
    // 5. Base au niveau de cette version (export d'une version plus ancienne)
    schema_migrate();
    set_setting('db_version', APP_VERSION);
    q('DELETE FROM ai_cache');
    audit('Import complet depuis un autre Centriva', 'transfer', null, ($m['name'] ?? '') . ' — export du ' . ($m['exported_at'] ?? '?') . ' (v' . ($m['app_version'] ?? '?') . ')');
    return $m + ['stats' => $stats, 'backup' => basename($backup)];
}
