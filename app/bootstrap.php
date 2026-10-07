<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);

// Fichier de configuration (CMD_CONFIG permet de pointer une autre configuration, ex. pour les tests)
$configFile = getenv('CMD_CONFIG') ?: ROOT . '/config.php';
if (!is_file($configFile)) {
    header('Location: install.php');
    exit;
}

$GLOBALS['config'] = require $configFile;

// Erreurs : journal dans storage/logs/ et page explicite plutôt qu'une erreur 500 muette
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(ROOT . '/storage') && (is_dir(ROOT . '/storage/logs') || @mkdir(ROOT . '/storage/logs', 0755))) {
    ini_set('error_log', ROOT . '/storage/logs/php-errors.log');
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
require_once APP . '/pdf.php';
require_once APP . '/cron.php';

define('APP_VERSION', trim((string)@file_get_contents(ROOT . '/VERSION')) ?: '1.0.0');

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('cmdcentres');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Mode maintenance pendant l'application d'une mise à jour
if (is_file(ROOT . '/storage/maintenance.flag') && filemtime(ROOT . '/storage/maintenance.flag') > time() - 600) {
    http_response_code(503);
    header('Retry-After: 30');
    exit('<!doctype html><meta charset="utf-8"><title>Maintenance</title><body style="font-family:system-ui;display:grid;place-items:center;height:100vh;background:#f4f6fb"><div style="text-align:center"><h1>Mise à jour en cours…</h1><p>L\'application sera de nouveau disponible dans quelques instants.</p></div>');
}

// Migration automatique de la base quand le code a été mis à jour
if (setting('db_version') !== APP_VERSION) {
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

header('Permissions-Policy: camera=(self)');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
