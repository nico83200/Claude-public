<?php

/*
 * Tâche planifiée de Jack&Cie, pour les hébergeurs qui demandent le chemin d'un fichier PHP
 * (Hostinger, OVH « Tâches planifiées »…) au lieu d'une ligne de commande. À lancer chaque minute.
 * Équivaut à : php artisan schedule:run
 *
 * Fonctionne aussi quand l'hébergeur lance le cron avec PHP CGI au lieu de PHP CLI.
 * Chaque passage est noté dans storage/logs/cron.log (affiché dans Administration › Notifications).
 * Placé hors du dossier public : le site ne le sert pas.
 */
if (isset($_SERVER['REQUEST_METHOD']) && PHP_SAPI !== 'cli') {
    // Appel depuis un navigateur : refusé.
    http_response_code(404);
    exit;
}

chdir(__DIR__);
@set_time_limit(0);
// L'application doit se comporter comme en ligne de commande, même lancée par PHP CGI.
putenv('APP_RUNNING_IN_CONSOLE=true');
$_ENV['APP_RUNNING_IN_CONSOLE'] = $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'true';
foreach (['STDIN' => 'php://stdin', 'STDOUT' => 'php://stdout', 'STDERR' => 'php://stderr'] as $const => $stream) {
    if (! defined($const)) {
        define($const, fopen($stream, $const === 'STDIN' ? 'r' : 'w'));
    }
}

$log = function (string $status) {
    @file_put_contents(__DIR__.'/storage/logs/cron.log', json_encode([
        'at' => date('c'), 'sapi' => PHP_SAPI, 'php' => PHP_VERSION, 'status' => $status,
    ], JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND | LOCK_EX);
};

try {
    define('LARAVEL_START', microtime(true));
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $status = $app->handleCommand(new Symfony\Component\Console\Input\ArgvInput(['artisan', 'schedule:run']));
    $log($status === 0 ? 'ok' : 'code '.$status);
    exit($status);
} catch (Throwable $e) {
    $log('erreur : '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
    exit(1);
}
