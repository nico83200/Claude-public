<?php
declare(strict_types=1);

/**
 * Initialisation commune : configuration, autoload, en-têtes de sécurité.
 */
const VS_VERSION = '1.0.0';
const VS_ROOT = __DIR__ . '/..';

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('VoyageStudio nécessite PHP 8.1 ou supérieur (version actuelle : ' . PHP_VERSION . ').');
}

spl_autoload_register(function (string $class): void {
    $map = [
        'ValidationException' => 'Repository', 'NotFoundException' => 'Repository', 'HttpException' => 'Api',
    ];
    $file = __DIR__ . '/' . ($map[$class] ?? $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

function vs_config_path(): string
{
    return VS_ROOT . '/config.php';
}

function vs_config(): ?array
{
    static $cfg = null;
    if ($cfg === null && is_file(vs_config_path())) {
        $cfg = require vs_config_path();
    }
    return $cfg;
}

function vs_db(): Database
{
    static $db = null;
    if ($db === null) {
        $cfg = vs_config();
        $db = new Database($cfg['db'] ?? ['driver' => 'sqlite']);
        // Migration légère et idempotente à chaque changement de version
        $marker = VS_ROOT . '/data/.schema-' . VS_VERSION . '-' . md5((string)filemtime(__DIR__ . '/Schema.php'));
        if (!is_file($marker)) {
            $db->migrate();
            @touch($marker);
        }
    }
    return $db;
}

function vs_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

$cfg = vs_config();
date_default_timezone_set($cfg['timezone'] ?? 'Europe/Paris');
ini_set('display_errors', !empty($cfg['debug']) ? '1' : '0');
