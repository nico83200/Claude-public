<?php

/*
 * Tâche planifiée de Jack&Cie, pour les hébergeurs qui demandent le chemin d'un fichier PHP
 * (ex. OVH « Tâches planifiées – Cron ») au lieu d'une ligne de commande.
 * À lancer chaque minute. Hors du dossier public : inaccessible depuis le navigateur.
 * Équivaut à : php artisan schedule:run
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(__DIR__);
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

exit($app->handleCommand(new Symfony\Component\Console\Input\ArgvInput(['artisan', 'schedule:run'])));
