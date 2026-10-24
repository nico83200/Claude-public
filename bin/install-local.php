<?php
/**
 * Installation locale rapide (SQLite + catalogue de démo) pour développer sans MySQL :
 *   php bin/install-local.php admin@example.com MotDePasse123
 *   php -S localhost:8000 -t public public/index.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Installer;

[$email, $password] = [$argv[1] ?? 'admin@example.com', $argv[2] ?? 'admin12345'];
@unlink(STORAGE_PATH . '/database.sqlite');
@unlink(STORAGE_PATH . '/installed.lock');

$db = ['driver' => 'sqlite', 'sqlite' => STORAGE_PATH . '/database.sqlite'];
$config = [
    'app'      => ['url' => 'http://localhost:8000', 'env' => 'local', 'debug' => true, 'key' => bin2hex(random_bytes(32))],
    'db'       => $db,
    'mail'     => ['transport' => 'log', 'admin_email' => $email, 'from_email' => 'boutique@example.com'],
    'stripe'   => ['secret_key' => getenv('STRIPE_SECRET') ?: '', 'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_local_test'],
    'printful' => ['api_token' => getenv('PRINTFUL_TOKEN') ?: '', 'webhook_token' => 'local-printful-token'],
    'cron'     => ['token' => 'local-cron-token'],
];

Config::set('db', $db);
Config::set('app.url', $config['app']['url']);
$pdo = Database::connect(Config::get('db'));
Installer::migrate($pdo, 'sqlite');
Database::setPdo($pdo);
App\Core\Migrator::markAllApplied();
require BASE_PATH . '/database/seed.php';
seed_database('Crinière Club', $email, true);
Database::insert('admins', ['email' => $email, 'name' => 'Admin', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'created_at' => now()]);
Installer::writeConfig($config);
Installer::lock();

echo "Installation locale terminée.\nAdmin : $email / $password\nLancez : php -S localhost:8000 -t public public/index.php\n";
