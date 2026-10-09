<?php
declare(strict_types=1);

/**
 * Opérations de la console NLapps sur les clients (création, reprise de l'installation existante,
 * suspension, mise à jour du code commune à tous, tâches planifiées). Chargé par console.php uniquement.
 */

require_once APP . '/install_demo.php';

/** Adresse publique d'un client vue depuis la requête en cours (console ou page de connexion) : adresse dédiée ou /<identifiant>/. */
function instance_guess_url(string $slug, array $hosts = []): ?string
{
    if (!empty($hosts[0])) {
        return 'https://' . $hosts[0] . '/';
    }
    if (empty($_SERVER['HTTP_HOST'])) {
        return null;
    }
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . instance_web_dir() . '/' . $slug . '/';
}

/** Exécute $fn dans le contexte d'un client (base, dossiers), puis revient au contexte de la console. */
function instance_run(string $slug, callable $fn): mixed
{
    instance_activate($slug);
    try {
        return $fn();
    } finally {
        instance_activate(null);
        $GLOBALS['config'] = [];
    }
}

/** Configuration de base de données saisie dans la console : SQLite (par défaut) ou MySQL / MariaDB. */
function instance_db_config(string $slug, array $in): array
{
    if (($in['driver'] ?? 'sqlite') === 'mysql') {
        $db = ['driver' => 'mysql', 'host' => trim((string)($in['host'] ?? '')) ?: 'localhost', 'port' => (int)($in['port'] ?? 3306) ?: 3306,
            'name' => trim((string)($in['name'] ?? '')), 'user' => trim((string)($in['user'] ?? '')), 'pass' => (string)($in['pass'] ?? '')];
        if ($db['name'] === '') {
            throw new RuntimeException('Indiquez le nom de la base MySQL du client.');
        }
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            if ($pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
                throw new RuntimeException('Cette base contient déjà une installation Centriva : utilisez une base vide, propre à ce client.');
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Connexion à la base MySQL impossible : ' . $e->getMessage());
        }
        return $db;
    }
    return ['driver' => 'sqlite', 'path' => instance_paths($slug)['storage'] . '/app.sqlite'];
}

function instance_write_config(string $slug, array $config): void
{
    $file = instance_paths($slug)['config'];
    @mkdir(dirname($file), 0750, true);
    file_put_contents($file, "<?php\n// Client « $slug » — généré par la console NLapps le " . date('d/m/Y H:i') . "\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
    @chmod($file, 0640);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
}

/** Adresses saisies (une par ligne ou séparées par des virgules), contrôlées et libres. */
function instance_parse_hosts(string $text, ?string $exceptSlug = null): array
{
    $hosts = [];
    foreach (preg_split('/[\s,;]+/', $text) as $h) {
        $h = instance_normalize_host($h);
        if ($h === '') {
            continue;
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d+)?$/', $h)) {
            throw new RuntimeException('Adresse invalide : ' . $h . ' (exemple attendu : achats.imss.fr).');
        }
        foreach (instances_registry() as $s => $i) {
            if ($s !== $exceptSlug && in_array($h, array_map('instance_normalize_host', (array)$i['hosts']), true)) {
                throw new RuntimeException('L\'adresse ' . $h . ' est déjà utilisée par le client « ' . $i['name'] . ' ».');
            }
        }
        $hosts[] = $h;
    }
    return array_values(array_unique($hosts)); // facultatif : sans adresse dédiée, l'espace est servi à centriva.fr/<identifiant>/
}

/**
 * Crée un client : dossiers, configuration, base, compte administrateur, données de départ
 * (catégories) ou de démonstration, accès à l'assistance NLapps.
 */
function instance_create(array $in): string
{
    $slug = strtolower(trim((string)($in['slug'] ?? '')));
    $name = trim((string)($in['name'] ?? ''));
    $registry = instances_registry();
    if (!instance_valid_slug($slug)) {
        throw new RuntimeException('Identifiant invalide : lettres minuscules, chiffres et tirets (ex. imss, sante-var), hors noms réservés (app, assets, admin…).');
    }
    if (isset($registry[$slug]) || is_dir(instances_dir() . '/' . $slug)) {
        throw new RuntimeException('L\'identifiant « ' . $slug . ' » est déjà pris.');
    }
    if ($name === '') {
        throw new RuntimeException('Indiquez le nom du client.');
    }
    $hosts = instance_parse_hosts((string)($in['hosts'] ?? ''));
    $email = mb_strtolower(trim((string)($in['admin_email'] ?? '')));
    $pass = (string)($in['admin_password'] ?? '');
    $first = trim((string)($in['admin_first_name'] ?? ''));
    $last = trim((string)($in['admin_last_name'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $first === '' || $last === '') {
        throw new RuntimeException('Administrateur du client : prénom, nom et e-mail valide obligatoires.');
    }
    if (mb_strlen($pass) < 10) {
        throw new RuntimeException('Mot de passe de l\'administrateur : 10 caractères minimum.');
    }
    $db = instance_db_config($slug, (array)($in['db'] ?? []));

    instance_prepare_dirs($slug);
    $config = ['db' => $db, 'app_name' => trim((string)($in['app_name'] ?? '')) ?: 'Centriva', 'timezone' => 'Europe/Paris', 'anthropic_api_key' => '', 'max_upload' => 4 * 1024 * 1024];
    if (trim((string)($in['hub_key'] ?? '')) !== '') {
        $config['support_hub_url'] = trim((string)($in['hub_url'] ?? '')) ?: 'https://nlapps.fr/assistance/api.php';
        $config['support_hub_key'] = trim((string)$in['hub_key']);
    }
    instance_write_config($slug, $config);
    // Le registre est écrit en dernier : un client à moitié créé n'est jamais servi
    $GLOBALS['new_hosts'] = $hosts;
    try {
        instance_run($slug, function () use ($email, $pass, $first, $last, $name, $in) {
            schema_install();
            tx(function () use ($email, $pass, $first, $last, $name, $in) {
                $adminId = insert('users', ['email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'first_name' => $first, 'last_name' => $last, 'job' => 'Achats', 'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
                install_base_data();
                if (!empty($in['demo'])) {
                    install_demo_data($adminId);
                }
                set_setting('company_name', $name);
            });
            set_setting('db_version', APP_VERSION);
            set_setting('cron_key', bin2hex(random_bytes(16)));
            if ($u = instance_guess_url($GLOBALS['instance'], $GLOBALS['new_hosts'] ?? [])) {
                set_setting('app_url', $u); // liens des e-mails envoyés avant la première visite
            }
            audit('Espace créé par la console NLapps', 'instance', null, $name);
        });
    } catch (Throwable $e) {
        instance_remove_files($slug);
        throw new RuntimeException('Création interrompue, rien n\'a été conservé : ' . $e->getMessage());
    }
    $registry[$slug] = ['name' => $name, 'hosts' => $hosts, 'suspended' => false, 'demo' => !empty($in['demo']) || !empty($in['public_demo']),
        'public_demo' => !empty($in['public_demo']), 'created_at' => date('Y-m-d H:i:s')];
    instances_save($registry);
    if (!empty($in['public_demo'])) {
        instance_demo_reset($slug); // comptes de démonstration (connexion en un clic)
    }
    return $slug;
}

/**
 * Reprend l'installation existante (config.php, storage/, uploads/ à la racine) comme premier client,
 * sans toucher à sa base : ses comptes, commandes et réglages sont conservés.
 */
function instance_adopt_current(string $slug, string $name, string $hostsText): string
{
    if (!is_file(ROOT . '/config.php')) {
        throw new RuntimeException('Aucune installation simple à reprendre (config.php absent).');
    }
    if (!instance_valid_slug($slug) || isset(instances_registry()[$slug]) || is_dir(instances_dir() . '/' . $slug)) {
        throw new RuntimeException('Identifiant invalide ou déjà pris.');
    }
    $hosts = instance_parse_hosts($hostsText);
    $config = require ROOT . '/config.php';
    if (($config['db']['driver'] ?? '') === 'sqlite' && str_starts_with((string)$config['db']['path'], ROOT . '/storage/')) {
        $config['db']['path'] = instance_paths($slug)['storage'] . substr((string)$config['db']['path'], strlen(ROOT . '/storage'));
    }
    instance_prepare_dirs($slug);
    $p = instance_paths($slug);
    // Fichiers privés (clé de chiffrement, factures, contrats, sauvegardes, vidéos…) puis fichiers publics
    instance_copy_dir(ROOT . '/storage', $p['storage'], ['maintenance.flag', 'update-pending.zip', 'cron.lock', 'logs',
        'console-auth.json', 'console-code.txt', 'console-attempts.json', 'clients-supprimes', 'console-update.zip']);
    foreach (['products', 'brand'] as $d) {
        instance_copy_dir(ROOT . "/uploads/$d", $p['uploads'] . "/$d", []);
    }
    instance_write_config($slug, $config);
    $registry = instances_registry();
    $registry[$slug] = ['name' => $name ?: (string)($config['app_name'] ?? $slug), 'hosts' => $hosts, 'suspended' => false, 'demo' => false, 'created_at' => date('Y-m-d H:i:s'), 'adopted' => true];
    instances_save($registry);
    if ($u = instance_guess_url($slug, $hosts)) {
        try {
            instance_run($slug, fn() => set_setting('app_url', $u));
        } catch (Throwable) {
        }
    }
    // L'ancienne configuration est mise de côté : toutes les adresses passent désormais par le registre
    @rename(ROOT . '/config.php', ROOT . '/storage/config.php.repris-' . date('Ymd-His'));
    return $slug;
}

function instance_copy_dir(string $from, string $to, array $skip): void
{
    if (!is_dir($from)) {
        return;
    }
    @mkdir($to, 0750, true);
    foreach (scandir($from) ?: [] as $f) {
        if ($f === '.' || $f === '..' || in_array($f, $skip, true)) {
            continue;
        }
        is_dir("$from/$f") ? instance_copy_dir("$from/$f", "$to/$f", []) : @copy("$from/$f", "$to/$f");
    }
}

function instance_remove_files(string $slug): void
{
    $rm = function (string $d) use (&$rm) {
        foreach (is_dir($d) ? (scandir($d) ?: []) : [] as $f) {
            if ($f !== '.' && $f !== '..') {
                is_dir("$d/$f") ? $rm("$d/$f") : @unlink("$d/$f");
            }
        }
        @rmdir($d);
    };
    if (instance_valid_slug($slug)) {
        $rm(instances_dir() . '/' . $slug);
        $rm(ROOT . '/uploads/i/' . $slug);
    }
}

/** Retire un client : ses dossiers sont archivés (zip) dans storage/ de la console, puis effacés. La base MySQL n'est pas supprimée. */
function instance_delete(string $slug): string
{
    $registry = instances_registry();
    if (!isset($registry[$slug])) {
        throw new RuntimeException('Client inconnu.');
    }
    @mkdir(ROOT . '/storage/clients-supprimes', 0750, true);
    $archive = ROOT . '/storage/clients-supprimes/' . $slug . '-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    instance_run($slug, function () use ($zip) {
        $dump = tempnam(sys_get_temp_dir(), 'inst');
        try {
            db_dump_to($dump);
            $zip->addFromString('__database.jsonl', (string)file_get_contents($dump));
        } catch (Throwable) {
        }
        @unlink($dump);
    });
    foreach ([instances_dir() . '/' . $slug => 'instance', ROOT . '/uploads/i/' . $slug => 'uploads'] as $dir => $prefix) {
        if (is_dir($dir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $zip->addFile($f->getPathname(), $prefix . '/' . ltrim(substr($f->getPathname(), strlen($dir)), '/'));
                }
            }
        }
    }
    $zip->addFromString('__registry.json', json_encode([$slug => $registry[$slug]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->close();
    unset($registry[$slug]);
    instances_save($registry);
    instance_remove_files($slug);
    return basename($archive);
}

/** Remet à zéro les données d'un espace de démonstration publique. */
function instance_demo_reset(string $slug): void
{
    if (empty(instances_registry()[$slug]['public_demo'])) {
        throw new RuntimeException('Cet espace n\'est pas une démo publique : ses données ne sont jamais effacées.');
    }
    instance_run($slug, fn() => demo_reset());
}

/** Chiffres clés d'un client pour le tableau de la console. */
function instance_stats(string $slug): array
{
    try {
        return instance_run($slug, function () {
            $lic = function_exists('licence_status') ? licence_status() : '';
            return [
                'ok' => true,
                'db_version' => (string)setting('db_version', '?'),
                'users' => (int)val("SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL"),
                'centers' => (int)val('SELECT COUNT(*) FROM centers WHERE active = 1'),
                'products' => (int)val('SELECT COUNT(*) FROM products WHERE active = 1'),
                'orders_month' => (int)val('SELECT COUNT(*) FROM purchase_orders WHERE created_at >= ?', [date('Y-m-01')]),
                'last_login' => val('SELECT MAX(last_login) FROM users'),
                'licence' => $lic,
                'driver' => db_driver(),
                'demo_reset_at' => setting('demo_reset_at'),
            ];
        });
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** Migre la base de chaque client au niveau du code installé. */
function instances_migrate_all(): array
{
    $out = [];
    foreach (array_keys(instances_registry()) as $slug) {
        try {
            instance_run($slug, function () {
                schema_migrate();
                set_setting('db_version', APP_VERSION);
            });
            $out[$slug] = 'ok';
        } catch (Throwable $e) {
            $out[$slug] = $e->getMessage();
        }
    }
    return $out;
}

/**
 * Met à jour le code commun à tous les clients : sauvegarde de chaque base et du code,
 * remplacement des fichiers, puis migration de chaque base. Retour arrière automatique en cas d'échec d'écriture.
 */
function instances_update_code(string $zipPath): array
{
    @set_time_limit(600);
    $info = update_inspect($zipPath);
    if (version_compare($info['version'], APP_VERSION, '<=')) {
        throw new RuntimeException('La version ' . $info['version'] . ' n\'est pas plus récente que la version installée (' . APP_VERSION . ').');
    }
    $stamp = 'v' . APP_VERSION . '-vers-v' . $info['version'] . '_' . date('Ymd-His');
    foreach (array_keys(instances_registry()) as $slug) {
        instance_run($slug, function () use ($stamp) {
            @mkdir(storage_path('backups'), 0750, true);
            db_dump_to(storage_path('backups/base_' . $stamp . '.jsonl'));
        });
    }
    // Sauvegarde du code (dans storage/ de la console)
    @mkdir(ROOT . '/storage/backups', 0750, true);
    $codeBackup = ROOT . '/storage/backups/code_' . $stamp . '.zip';
    $zip = new ZipArchive();
    $zip->open($codeBackup, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach (code_files($info['vendor']) as $rel) {
        $zip->addFile(ROOT . '/' . $rel, 'files/' . $rel);
    }
    $added = array_values(array_filter($info['files'], fn($rel) => !file_exists(ROOT . '/' . $rel)));
    $zip->addFromString('__added.json', json_encode($added));
    $zip->close();

    maintenance(true);
    $src = new ZipArchive();
    $src->open($zipPath);
    try {
        $ordered = $info['files'];
        uasort($ordered, fn($a, $b) => ($a === 'VERSION') <=> ($b === 'VERSION'));
        foreach ($ordered as $idx => $rel) {
            $content = $src->getFromIndex((int)$idx);
            if ($content === false) {
                throw new RuntimeException('Lecture impossible : ' . $rel);
            }
            write_file_atomic($rel, $content);
        }
    } catch (Throwable $e) {
        $src->close();
        try {
            backup_restore_files($codeBackup);
        } catch (Throwable) {
        }
        maintenance(false);
        throw new RuntimeException('Échec de la mise à jour, l\'ancienne version a été restaurée : ' . $e->getMessage());
    }
    $src->close();
    // Fichiers racine livrés dans app/root/
    foreach (glob(APP . '/root/*') ?: [] as $f) {
        @copy($f, ROOT . '/' . basename($f));
    }
    maintenance(false);
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    return $info + ['code_backup' => basename($codeBackup)];
}

/**
 * Tâches planifiées de tous les clients actifs. Chaque client tourne dans un processus séparé
 * (php cron.php avec CMD_INSTANCE) ; à défaut (fonction proc_open désactivée), dans ce processus.
 */
function instances_cron_all(bool $force = false): array
{
    $out = [];
    foreach (instances_registry() as $slug => $i) {
        if (!empty($i['suspended'])) {
            $out[$slug] = 'suspendu';
            continue;
        }
        if (function_exists('proc_open') && PHP_BINARY !== '' && is_executable(PHP_BINARY) && !str_contains((string)ini_get('disable_functions'), 'proc_open')) {
            $cmd = [PHP_BINARY, ROOT . '/cron.php'];
            if ($force) {
                $cmd[] = '--force';
            }
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT, array_merge(getenv(), ['CMD_INSTANCE' => $slug]));
            if (is_resource($p)) {
                $res = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($p);
                $out[$slug] = $code === 0 ? 'ok' : trim(mb_substr($res, 0, 300));
                continue;
            }
        }
        try {
            instance_run($slug, function () use ($force) {
                if (setting('db_version') !== APP_VERSION) {
                    schema_migrate();
                    set_setting('db_version', APP_VERSION);
                }
                cron_run($force);
            });
            $out[$slug] = 'ok';
        } catch (Throwable $e) {
            $out[$slug] = $e->getMessage();
        }
    }
    return $out;
}
