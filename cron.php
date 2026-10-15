<?php

use Symfony\Component\Console\Input\ArgvInput;

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

// Le cron de certains hébergeurs (Hostinger…) utilise une version de PHP plus ancienne que le site :
// relance avec PHP 8.3 ou plus s'il est installé sur le serveur.
if (version_compare(PHP_VERSION, '8.3.0', '<') && ! getenv('JACKCIE_CRON_RELAUNCHED')) {
    // Même version que le site d'abord (8.3), puis plus récentes.
    $candidates = ['/opt/alt/php83/usr/bin/php', '/usr/bin/php8.3', '/usr/local/bin/php83', '/opt/cpanel/ea-php83/root/usr/bin/php', '/usr/local/php8.3/bin/php',
        '/opt/alt/php84/usr/bin/php', '/usr/bin/php8.4', '/usr/local/bin/php84', '/opt/cpanel/ea-php84/root/usr/bin/php', '/usr/local/php8.4/bin/php',
        '/opt/alt/php85/usr/bin/php', '/usr/bin/php8.5', '/usr/local/bin/php85', '/opt/cpanel/ea-php85/root/usr/bin/php', '/usr/local/php8.5/bin/php'];
    $bin = null;
    foreach ($candidates as $c) {
        if (@is_file($c) && @is_executable($c)) {
            $bin = $c;
            break;
        }
    }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    $can = fn ($f) => function_exists($f) && ! in_array($f, $disabled, true);
    if ($bin) {
        putenv('JACKCIE_CRON_RELAUNCHED=1');
        $cmd = 'JACKCIE_CRON_RELAUNCHED=1 '.escapeshellarg($bin).' '.escapeshellarg(__FILE__).' 2>&1';
        $code = null;
        if ($can('passthru')) {
            passthru($cmd, $code);
        } elseif ($can('system')) {
            system($cmd, $code);
        } elseif ($can('exec')) {
            exec($cmd, $out, $code);
        } elseif ($can('proc_open')) {
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (is_resource($proc)) {
                stream_get_contents($pipes[1]);
                $code = proc_close($proc);
            }
        } elseif ($can('shell_exec')) {
            shell_exec($cmd);
            $code = 0;
        }
        if ($code !== null) {
            exit((int) $code);
        }
        $log('erreur : le cron utilise PHP '.PHP_VERSION.' et ne peut pas relancer '.$bin.' (fonctions désactivées par l\'hébergeur). Remplacez la tâche par la commande : '.$bin.' '.__FILE__);
        exit(1);
    }
    $log('erreur : le cron utilise PHP '.PHP_VERSION.' et aucun PHP 8.3 n\'est visible pour lui. Choisissez PHP 8.3 pour la tâche cron chez l\'hébergeur, ou la commande indiquée dans Administration › Notifications.');
    exit(1);
}

try {
    define('LARAVEL_START', microtime(true));
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $status = $app->handleCommand(new ArgvInput(['artisan', 'schedule:run']));
    $log($status === 0 ? 'ok' : 'code '.$status);
    exit($status);
} catch (Throwable $e) {
    $log('erreur : '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')');
    exit(1);
}
