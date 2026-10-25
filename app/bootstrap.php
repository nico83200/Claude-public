<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('CONFIG_FILE', BASE_PATH . '/config/config.php');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_PATH . '/helpers.php';

App\Core\Config::load(CONFIG_FILE);

date_default_timezone_set((string) config('app.timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
// Dossiers de travail : recréés s'ils manquent (une extraction de ZIP ou un déploiement peut omettre
// les dossiers vides ; sans storage/cache, la tâche planifiée et les mises à jour échouent).
if (!is_dir(STORAGE_PATH . '/cache') || !is_dir(STORAGE_PATH . '/logs')) {
    foreach (['cache', 'logs', 'backups', 'updates', 'ai-portraits', 'customer-photos', 'designs'] as $dir) {
        if (!is_dir(STORAGE_PATH . '/' . $dir)) {
            @mkdir(STORAGE_PATH . '/' . $dir, 0755, true);
        }
    }
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

// Clés et jetons des intégrations : la base de données fait référence (voir App\Core\Secrets).
if (is_file(CONFIG_FILE)) {
    try {
        App\Core\Secrets::apply();
    } catch (\Throwable) {
        // Base indisponible : on garde les valeurs de config/config.php.
    }
}
