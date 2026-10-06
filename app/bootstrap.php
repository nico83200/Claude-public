<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);

if (!is_file(ROOT . '/config.php')) {
    header('Location: install.php');
    exit;
}

$GLOBALS['config'] = require ROOT . '/config.php';
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

define('APP_VERSION', trim((string)@file_get_contents(ROOT . '/VERSION')) ?: '1.0.0');

if (session_status() === PHP_SESSION_NONE) {
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
        set_setting('db_version', APP_VERSION);
    } catch (Throwable $e) {
        error_log('[migration] ' . $e->getMessage());
    }
}

header('Permissions-Policy: camera=(self)');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
