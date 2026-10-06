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

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
