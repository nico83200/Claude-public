<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Installer;
use App\Core\Migrator;
use App\Core\View;

/** Assistant d'installation : actif uniquement tant que storage/installed.lock n'existe pas. */
final class InstallController
{
    public function show(array $errors = [], array $old = []): void
    {
        echo View::render('install/index', [
            'checks' => self::checks(),
            'errors' => $errors,
            'old'    => $old + [
                'app_url'  => self::guessUrl(),
                'db_host'  => 'localhost',
                'db_port'  => '3306',
                'shop_name' => 'Crinière & Co',
            ],
        ], null);
    }

    public function store(): void
    {
        verify_csrf();
        $in = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $_POST);
        $errors = [];
        foreach (['app_url' => 'URL du site', 'shop_name' => 'Nom de la boutique', 'admin_email' => 'E-mail administrateur', 'admin_password' => 'Mot de passe'] as $k => $label) {
            if (empty($in[$k])) {
                $errors[] = "$label obligatoire.";
            }
        }
        if (!filter_var($in['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail administrateur invalide.';
        }
        if (mb_strlen($in['admin_password'] ?? '') < 10) {
            $errors[] = 'Le mot de passe doit contenir au moins 10 caractères.';
        }
        $driver = ($in['db_driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
        if ($driver === 'mysql' && (empty($in['db_name']) || empty($in['db_user']))) {
            $errors[] = 'Nom de base et utilisateur MySQL obligatoires.';
        }
        if ($errors) {
            $this->show($errors, $in);
            return;
        }

        $db = [
            'driver' => $driver,
            'host'   => $in['db_host'] ?: 'localhost',
            'port'   => (int) ($in['db_port'] ?: 3306),
            'name'   => $in['db_name'] ?? '',
            'user'   => $in['db_user'] ?? '',
            'pass'   => $in['db_pass'] ?? '',
            'sqlite' => STORAGE_PATH . '/database.sqlite',
        ];

        try {
            $pdo = Database::connect($db);
        } catch (\Throwable $e) {
            $this->show(['Connexion à la base impossible : ' . $e->getMessage()], $in);
            return;
        }

        $config = [
            'app' => [
                'name'  => $in['shop_name'],
                'url'   => rtrim($in['app_url'], '/'),
                'env'   => 'production',
                'debug' => false,
                'key'   => bin2hex(random_bytes(32)),
            ],
            'db'       => $db,
            'stripe'   => ['secret_key' => $in['stripe_secret'] ?? '', 'publishable_key' => $in['stripe_public'] ?? '', 'webhook_secret' => $in['stripe_webhook'] ?? ''],
            'printful' => ['api_token' => $in['printful_token'] ?? '', 'store_id' => $in['printful_store'] ?? '', 'auto_confirm' => false, 'webhook_token' => bin2hex(random_bytes(20))],
            'mail'     => [
                'transport'   => 'smtp',
                'host'        => $in['mail_host'] ?: 'smtp.hostinger.com',
                'port'        => (int) ($in['mail_port'] ?: 465),
                'encryption'  => ((int) ($in['mail_port'] ?: 465)) === 587 ? 'tls' : 'ssl',
                'username'    => $in['mail_user'] ?? '',
                'password'    => $in['mail_pass'] ?? '',
                'from_email'  => $in['mail_user'] ?? '',
                'from_name'   => $in['shop_name'],
                'admin_email' => $in['admin_email'],
            ],
            'cron' => ['token' => bin2hex(random_bytes(20))],
        ];

        try {
            Installer::migrate($pdo, $driver);
            Database::setPdo($pdo);
            Migrator::markAllApplied();
            Config::set('db', $db);
            Config::set('app.url', $config['app']['url']);
            require_once BASE_PATH . '/database/seed.php';
            seed_database($in['shop_name'], (string) $in['admin_email'], !empty($in['demo']));
            Database::insert('admins', [
                'email'         => mb_strtolower($in['admin_email']),
                'name'          => 'Administrateur',
                'password_hash' => password_hash($in['admin_password'], PASSWORD_DEFAULT),
                'created_at'    => now(),
            ]);
            Installer::writeConfig($config);
            Installer::lock();
        } catch (\Throwable $e) {
            $this->show(['Installation interrompue : ' . $e->getMessage() . ' — si les tables existent déjà, videz la base puis recommencez.'], $in);
            return;
        }

        echo View::render('install/done', ['config' => $config], null);
    }

    private static function checks(): array
    {
        $writable = static fn (string $p) => is_writable(BASE_PATH . '/' . $p);
        return [
            'PHP ≥ 8.1 (' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'Extension PDO MySQL'            => extension_loaded('pdo_mysql'),
            'Extension cURL'                 => extension_loaded('curl'),
            'Extension GD + FreeType'        => function_exists('imagettftext'),
            'Extension mbstring'             => extension_loaded('mbstring'),
            'Extension intl'                 => extension_loaded('intl'),
            'Extension zip (mises à jour)'   => class_exists(\ZipArchive::class),
            'Dossier config/ inscriptible'   => $writable('config'),
            'Dossier storage/ inscriptible'  => $writable('storage') && $writable('storage/logs') && $writable('storage/designs'),
            'Dossier public/uploads/ inscriptible' => $writable('public/uploads/products') && $writable('public/uploads/print'),
        ];
    }

    private static function guessUrl(): string
    {
        return base_url();
    }
}
