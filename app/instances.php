<?php
declare(strict_types=1);

/**
 * Multi-clients : un seul exemplaire du code sert plusieurs clients, chacun avec sa base de données,
 * ses fichiers (storage, photos, logo), ses comptes et sa licence. Le client est reconnu à l'adresse
 * utilisée (centriva.fr/imss/, ou une adresse dédiée comme achats.imss.fr).
 *
 *   instances/registry.php          liste des clients : slug => [name, hosts[], suspended, created_at]
 *   instances/<slug>/config.php     configuration du client (base de données, nom, fuseau…)
 *   instances/<slug>/storage/       fichiers privés (sauvegardes, factures, contrats, journaux…)
 *   uploads/i/<slug>/               fichiers publics (photos d'articles, logo)
 *
 * Sans registre, l'application fonctionne comme avant (config.php, storage/ et uploads/ à la racine).
 */

function instances_dir(): string
{
    return ROOT . '/instances';
}

function instances_enabled(): bool
{
    return is_file(instances_dir() . '/registry.php');
}

function instances_registry(): array
{
    if (!instances_enabled()) {
        return [];
    }
    $r = include instances_dir() . '/registry.php';
    return is_array($r) ? $r : [];
}

function instances_save(array $registry): void
{
    ksort($registry);
    $dir = instances_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $tmp = $dir . '/registry.php.' . bin2hex(random_bytes(4));
    file_put_contents($tmp, "<?php\n// Clients Centriva — géré par la console NLapps (console.php)\nreturn " . var_export($registry, true) . ";\n", LOCK_EX);
    rename($tmp, $dir . '/registry.php');
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($dir . '/registry.php', true);
    }
}

/** Noms réservés : dossiers et fichiers de l'application, qui ne peuvent pas servir d'identifiant d'espace. */
const INSTANCE_RESERVED = ['app', 'assets', 'uploads', 'storage', 'instances', 'vendor', 'tools', 'tests', 'dist', 'support-hub', 'assistance',
    'console', 'index', 'install', 'cron', 'offline', 'sw', 'manifest', 'api', 'admin', 'www', 'static', 'centriva', 'nlapps'];

function instance_valid_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $slug) && !in_array($slug, INSTANCE_RESERVED, true);
}

/** Dossier web de l'installation (« » à la racine du domaine, « /centriva » dans un sous-dossier). */
function instance_web_dir(): string
{
    return rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
}

/**
 * Espace indiqué dans l'adresse : centriva.fr/<espace>/… (le serveur web sert le même code, voir .htaccess).
 * Renvoie [identifiant, reste du chemin] ou null si l'adresse ne commence pas par un identifiant d'espace.
 */
function instance_from_path(string $uri): ?array
{
    $path = (string)parse_url($uri, PHP_URL_PATH);
    $dir = instance_web_dir();
    if ($dir !== '' && str_starts_with($path, $dir . '/')) {
        $path = substr($path, strlen($dir));
    }
    if (!preg_match('#^/([a-z0-9][a-z0-9-]{0,38}[a-z0-9]?)(/.*)?$#', $path, $m) || !instance_valid_slug($m[1])) {
        return null;
    }
    return [$m[1], $m[2] ?? ''];
}

/** Adresse publique de l'espace courant quand il est servi par son chemin (https://centriva.fr/imss/). */
function instance_public_url(): ?string
{
    if (empty($GLOBALS['instance_by_path']) || empty($_SERVER['HTTP_HOST'])) {
        return null;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . instance_web_dir() . '/' . current_instance() . '/';
}

function instance_normalize_host(string $host): string
{
    $host = strtolower(trim($host));
    $host = preg_replace('#^https?://#', '', $host);
    return rtrim(explode('/', $host)[0], '.');
}

/** Client correspondant à une adresse (port ignoré, « www. » toléré). */
function instance_for_host(string $host): ?string
{
    $host = preg_replace('/:\d+$/', '', instance_normalize_host($host));
    foreach (instances_registry() as $slug => $i) {
        foreach ((array)($i['hosts'] ?? []) as $h) {
            $h = preg_replace('/:\d+$/', '', instance_normalize_host((string)$h));
            if ($host === $h || $host === 'www.' . $h) {
                return (string)$slug;
            }
        }
    }
    return null;
}

/** Chemins d'un client (ou de l'installation simple si $slug est vide). */
function instance_paths(?string $slug): array
{
    if ($slug === null || $slug === '') {
        return ['config' => ROOT . '/config.php', 'storage' => ROOT . '/storage', 'uploads' => ROOT . '/uploads', 'uploads_url' => 'uploads'];
    }
    return [
        'config' => instances_dir() . "/$slug/config.php",
        'storage' => instances_dir() . "/$slug/storage",
        'uploads' => ROOT . "/uploads/i/$slug",
        'uploads_url' => "uploads/i/$slug",
    ];
}

/**
 * Bascule le contexte sur un client : configuration, chemins, connexion à la base, caches.
 * Utilisé au démarrage de chaque requête et par la console NLapps pour agir client par client.
 */
function instance_activate(?string $slug): void
{
    $paths = instance_paths($slug);
    $GLOBALS['instance'] = $slug ?: null;
    $GLOBALS['paths'] = $paths;
    $GLOBALS['config'] = is_file($paths['config']) ? require $paths['config'] : [];
    if (function_exists('db_reset')) {
        db_reset();
    }
    if (function_exists('setting')) {
        setting('', null, true);
    }
}

function current_instance(): ?string
{
    return $GLOBALS['instance'] ?? null;
}

function current_instance_info(): ?array
{
    $s = current_instance();
    return $s ? (instances_registry()[$s] ?? null) : null;
}

/** Dossier privé du client courant (storage/), avec un sous-chemin éventuel. */
function storage_path(string $sub = ''): string
{
    return ($GLOBALS['paths']['storage'] ?? ROOT . '/storage') . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}

/** Dossier public des fichiers envoyés (photos, logo) du client courant. */
function uploads_path(string $sub = ''): string
{
    return ($GLOBALS['paths']['uploads'] ?? ROOT . '/uploads') . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}

/** Adresse relative de ces fichiers pour le navigateur. */
function uploads_url(string $sub = ''): string
{
    return ($GLOBALS['paths']['uploads_url'] ?? 'uploads') . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}

/** Crée les dossiers d'un client, protégés (pas d'exécution de script dans les fichiers envoyés). */
function instance_prepare_dirs(?string $slug): void
{
    $p = instance_paths($slug);
    foreach (['', '/logs', '/backups'] as $d) {
        @mkdir($p['storage'] . $d, 0750, true);
    }
    if (!is_file($p['storage'] . '/.htaccess')) {
        @file_put_contents($p['storage'] . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $guard = is_file(ROOT . '/uploads/products/.htaccess') ? (string)file_get_contents(ROOT . '/uploads/products/.htaccess')
        : "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n";
    foreach (['products', 'brand'] as $d) {
        @mkdir($p['uploads'] . "/$d", 0755, true);
        if (!is_file($p['uploads'] . "/$d/.htaccess")) {
            @file_put_contents($p['uploads'] . "/$d/.htaccess", $guard);
        }
    }
}

/** Page affichée quand l'adresse ne correspond à aucun client, ou quand le client est suspendu. */
function instance_unavailable(string $title, string $text, int $code = 404): never
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title>'
        . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f6fb;color:#1e1b4b">'
        . '<div style="background:#fff;border-radius:16px;padding:2rem 2.2rem;max-width:480px;box-shadow:0 8px 30px rgba(30,27,75,.1);text-align:center">'
        . '<h1 style="margin-top:0;font-size:1.4rem">' . htmlspecialchars($title) . '</h1><p style="color:#64748b;line-height:1.5">' . $text . '</p>'
        . '<p style="color:#94a3b8;font-size:.85rem;margin-bottom:0">Centriva · NLapps</p></div>');
}
