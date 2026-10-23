<?php
/**
 * Tâche planifiée — à déclarer dans hPanel > Avancé > Tâches Cron :
 *   /usr/bin/php /home/<UTILISATEUR>/domains/<DOMAINE>/public_html/cron/cron.php
 * Fréquence conseillée : toutes les 15 minutes.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (!App\Core\Config::installed()) {
    fwrite(STDERR, "Boutique non installée.\n");
    exit(1);
}

echo implode(PHP_EOL, App\Services\Cron::run()) . PHP_EOL;
