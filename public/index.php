<?php
declare(strict_types=1);

use App\Controllers\InstallController;
use App\Core\Config;

// Serveur de développement PHP (php -S) : servir directement les fichiers statiques.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name('cc_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if (!Config::installed()) {
        $installer = new InstallController();
        $method === 'POST' && $path === '/install' ? $installer->store() : $installer->show();
        exit;
    }

    $router = require APP_PATH . '/routes.php';
    $router->dispatch($method, $path);
} catch (\Throwable $e) {
    log_message('error', $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'path' => $path]);
    if (config('app.debug')) {
        http_response_code(500);
        echo '<pre style="padding:2rem;white-space:pre-wrap">' . e((string) $e) . '</pre>';
        exit;
    }
    abort(500, 'Une erreur est survenue. Notre équipe a été prévenue.');
}
