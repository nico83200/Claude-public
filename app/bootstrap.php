<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);

require_once APP . '/instances.php';
require_once APP . '/central.php';
require_once APP . '/platform.php';

// Console NLapps (console.php) : aucun client chargé au départ, elle bascule de l'un à l'autre
$console = defined('NL_CONSOLE');

// Client servi : CMD_INSTANCE (ligne de commande), sinon, quand plusieurs clients sont installés,
// l'adresse dédiée du client (imss.exemple.fr) ou le début du chemin (centriva.fr/imss/…)
$slug = $console ? null : (getenv('CMD_INSTANCE') ?: null);
if (!$console && $slug === null && !getenv('CMD_CONFIG') && instances_enabled() && PHP_SAPI !== 'cli') {
    $slug = instance_for_host((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($slug === null && ($fromPath = instance_from_path((string)($_SERVER['REQUEST_URI'] ?? '/')))) {
        if (!isset(instances_registry()[$fromPath[0]])) {
            instance_unavailable('Espace introuvable', 'Aucun espace Centriva ne porte l\'identifiant « ' . htmlspecialchars($fromPath[0]) . ' ». <a href="' . instance_web_dir() . '/?changer=1">Saisir un autre identifiant</a>');
        }
        $slug = $fromPath[0];
        $GLOBALS['instance_by_path'] = true;
        if ($fromPath[1] === '') { // centriva.fr/imss → centriva.fr/imss/ (les liens de l'application sont relatifs)
            header('Location: ' . instance_web_dir() . '/' . $slug . '/', true, 301);
            exit;
        }
    }
    if ($slug === null && !is_file(ROOT . '/config.php')) {
        $console = true; // page de connexion commune à tous les clients (fin de ce fichier)
        $GLOBALS['central_login'] = true;
    }
}
if ($slug !== null) {
    $info = instances_registry()[$slug] ?? null;
    if (!$info || !is_file(instance_paths($slug)['config'])) {
        instance_unavailable('Espace introuvable', 'Cet espace Centriva n\'existe pas ou a été supprimé.');
    }
    if (!empty($info['suspended']) && PHP_SAPI !== 'cli') {
        instance_unavailable('Espace momentanément indisponible', 'L\'accès à cet espace Centriva est suspendu. Contactez NLapps pour le rétablir.', 503);
    }
}

// Fichier de configuration (CMD_CONFIG permet de pointer une autre configuration, ex. pour les tests)
$configFile = getenv('CMD_CONFIG') ?: instance_paths($slug)['config'];
if (!is_file($configFile) && !$console) {
    header('Location: install.php');
    exit;
}
instance_activate($slug);
$GLOBALS['config'] = is_file($configFile) ? require $configFile : [];

// Erreurs : journal dans storage/logs/ et page explicite plutôt qu'une erreur 500 muette
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(storage_path()) && (is_dir(storage_path('logs')) || @mkdir(storage_path('logs'), 0755))) {
    ini_set('error_log', storage_path('logs/php-errors.log'));
}
function app_error_page(string $message): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . "\n");
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    // Le détail n'est montré qu'aux administrateurs connectés (sinon : journal uniquement)
    $isAdmin = false;
    try {
        $isAdmin = function_exists('is_admin') && session_status() === PHP_SESSION_ACTIVE && is_admin();
    } catch (Throwable) {
    }
    echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><body style="font-family:system-ui;background:#f4f6fb;display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<div style="background:#fff;border-radius:14px;padding:2rem;max-width:640px;box-shadow:0 4px 20px rgba(0,0,0,.08)"><h1 style="margin-top:0">Une erreur est survenue</h1>'
        . '<p>L\'opération n\'a pas pu aboutir. Le détail a été enregistré dans <code>storage/logs/php-errors.log</code>.</p>'
        . ($isAdmin ? '<pre style="white-space:pre-wrap;background:#fff4f4;color:#a40000;padding:1rem;border-radius:8px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre>' : '')
        . '<p><a href="javascript:history.back()">← Revenir à la page précédente</a></p></div>';
}
set_exception_handler(function (Throwable $e) {
    $msg = get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    error_log('[exception] ' . $msg);
    app_error_page($msg);
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        app_error_page($err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')');
    }
});
date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Europe/Paris');
mb_internal_encoding('UTF-8');

if (is_file(ROOT . '/vendor/autoload.php')) {
    require_once ROOT . '/vendor/autoload.php';
}

require_once APP . '/db.php';
require_once APP . '/helpers.php';
require_once APP . '/auth.php';
require_once APP . '/domain.php';
require_once APP . '/search.php';
require_once APP . '/schema.php';
require_once APP . '/stock.php';
require_once APP . '/notify.php';
require_once APP . '/updater.php';
require_once APP . '/features.php';
require_once APP . '/cleanup.php';
require_once APP . '/spreadsheet.php';
require_once APP . '/barcode.php';
require_once APP . '/licence.php';
require_once APP . '/reports.php';
require_once APP . '/security.php';
require_once APP . '/import.php';
require_once APP . '/support.php';
require_once APP . '/videos.php';
require_once APP . '/contracts.php';
require_once APP . '/demo.php';
require_once APP . '/onboarding.php';
require_once APP . '/transfer.php';
require_once APP . '/pdf.php';
require_once APP . '/cron.php';

define('APP_VERSION', trim((string)@file_get_contents(ROOT . '/VERSION')) ?: '1.0.0');

if (!$console && session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('cmdcentres' . ($slug ? '_' . str_replace('-', '_', $slug) : ''));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => !empty($GLOBALS['instance_by_path']) ? instance_web_dir() . '/' . $slug . '/' : '/', // cookie limité à l'espace
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Une session ouverte chez un client n'est jamais valable chez un autre
    if (($_SESSION['instance'] ?? $slug) !== $slug) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['instance'] = $slug;
    // Espace servi par son chemin : mémorisé sur l'appareil (centriva.fr mène directement à l'espace)
    if (!empty($GLOBALS['instance_by_path']) && ($_COOKIE['centriva_espace'] ?? '') !== $slug) {
        setcookie('centriva_espace', (string)$slug, ['expires' => time() + 400 * 86400, 'path' => instance_web_dir() . '/', 'samesite' => 'Lax', 'secure' => $secure, 'httponly' => true]);
    }
}

// Mode maintenance pendant l'application d'une mise à jour
if (is_file(ROOT . '/storage/maintenance.flag') && filemtime(ROOT . '/storage/maintenance.flag') > time() - 600) {
    http_response_code(503);
    header('Retry-After: 30');
    exit('<!doctype html><meta charset="utf-8"><title>Maintenance</title><body style="font-family:system-ui;display:grid;place-items:center;height:100vh;background:#f4f6fb"><div style="text-align:center"><h1>Mise à jour en cours…</h1><p>L\'application sera de nouveau disponible dans quelques instants.</p></div>');
}

// Migration automatique de la base quand le code a été mis à jour
if (!$console && setting('db_version') !== APP_VERSION) {
    try {
        schema_migrate();
        // Fichiers racine livrés dans app/root/ par le paquet de mise à jour
        foreach (glob(APP . '/root/*') ?: [] as $src) {
            $dest = ROOT . '/' . basename($src);
            if (!is_file($dest) || md5_file($dest) !== md5_file($src)) {
                @copy($src, $dest);
            }
        }
        set_setting('db_version', APP_VERSION);
        if (!setting('cron_key')) {
            set_setting('cron_key', bin2hex(random_bytes(16)));
        }
    } catch (Throwable $e) {
        error_log('[migration] ' . $e->getMessage());
    }
}

// Adresse publique de l'espace (centriva.fr/imss/) retenue pour les liens des e-mails envoyés par les tâches planifiées
if (!$console && ($pub = instance_public_url()) && setting('app_url') !== $pub) {
    set_setting('app_url', $pub);
}

header('Permissions-Policy: camera=(self)');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// centriva.fr sans espace : connexion commune (e-mail + mot de passe), qui ouvre la session dans l'espace du bon client
if (!empty($GLOBALS['central_login'])) {
    require APP . (isset($_GET['paiement']) || isset($_GET['webhook']) ? '/paiement.php' : '/central_login.php'); // abonnements en ligne (Stripe)
    exit;
}
