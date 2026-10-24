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
                'shop_name' => 'Crinière Club',
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

        // Base déjà utilisée par la boutique ? On s'y reconnecte sans rien effacer.
        $existing = Installer::isShopDatabase($pdo);
        $backup = $existing ? Installer::restoreConfig($pdo) : [];
        $keep = static fn (string $key, mixed $fromForm, mixed $default = '') => ($fromForm !== null && $fromForm !== '')
            ? $fromForm
            : (self::dig($backup, $key) ?? $default);

        $mailPort = (int) $keep('mail.port', $in['mail_port'] ?? '', 465);
        $config = [
            'app' => [
                'name'  => $in['shop_name'],
                'url'   => rtrim($in['app_url'], '/'),
                'env'   => 'production',
                'debug' => false,
                'key'   => self::dig($backup, 'app.key') ?: bin2hex(random_bytes(32)),
            ],
            'db'       => $db,
            'stripe'   => [
                'secret_key'      => $keep('stripe.secret_key', $in['stripe_secret'] ?? ''),
                'publishable_key' => $keep('stripe.publishable_key', $in['stripe_public'] ?? ''),
                'webhook_secret'  => $keep('stripe.webhook_secret', $in['stripe_webhook'] ?? ''),
            ],
            'printful' => [
                'api_token'     => $keep('printful.api_token', $in['printful_token'] ?? ''),
                'store_id'      => $keep('printful.store_id', $in['printful_store'] ?? ''),
                'auto_confirm'  => (bool) (self::dig($backup, 'printful.auto_confirm') ?? false),
                'webhook_token' => self::dig($backup, 'printful.webhook_token') ?: bin2hex(random_bytes(20)),
            ],
            'mail'     => [
                'transport'   => self::dig($backup, 'mail.transport') ?: 'smtp',
                'host'        => $keep('mail.host', $in['mail_host'] ?? '', 'smtp.hostinger.com'),
                'port'        => $mailPort,
                'encryption'  => $mailPort === 587 ? 'tls' : 'ssl',
                'username'    => $keep('mail.username', $in['mail_user'] ?? ''),
                'password'    => $keep('mail.password', $in['mail_pass'] ?? ''),
                'from_email'  => $keep('mail.from_email', $in['mail_user'] ?? ''),
                'from_name'   => $in['shop_name'],
                'admin_email' => $keep('mail.admin_email', $in['admin_email']),
            ],
            'brevo'     => self::dig($backup, 'brevo') ?: ['api_key' => '', 'list_id' => 0],
            'analytics' => self::dig($backup, 'analytics') ?: ['ga4_id' => '', 'meta_pixel_id' => ''],
            'cron'      => ['token' => self::dig($backup, 'cron.token') ?: bin2hex(random_bytes(20))],
        ];

        try {
            Database::setPdo($pdo);
            Config::set('db', $db);
            Config::set('app.url', $config['app']['url']);
            $email = mb_strtolower($in['admin_email']);
            $hash = password_hash($in['admin_password'], PASSWORD_DEFAULT);

            if ($existing) {
                // Reconnexion : données conservées, migrations éventuelles appliquées, accès admin (ré)initialisé.
                $applied = Migrator::run();
                $adminId = Database::value('SELECT id FROM admins WHERE email = ?', [$email]);
                $adminId
                    ? Database::update('admins', ['password_hash' => $hash], 'id = :id', ['id' => $adminId])
                    : Database::insert('admins', ['email' => $email, 'name' => 'Administrateur', 'password_hash' => $hash, 'created_at' => now()]);
                log_message('install', 'Boutique existante reconnectée', ['migrations' => $applied, 'config_restauree' => (bool) $backup]);
            } else {
                Installer::migrate($pdo, $driver);
                Migrator::markAllApplied();
                require_once BASE_PATH . '/database/seed.php';
                seed_database($in['shop_name'], $email, !empty($in['demo']));
                Database::insert('admins', ['email' => $email, 'name' => 'Administrateur', 'password_hash' => $hash, 'created_at' => now()]);
            }
            Installer::writeConfig($config);
            Installer::backupConfig($pdo, $config);
            Installer::lock();
        } catch (\Throwable $e) {
            $this->show(['Installation interrompue : ' . $e->getMessage()], $in);
            return;
        }

        echo View::render('install/done', ['config' => $config, 'reconnected' => $existing, 'restored' => (bool) $backup], null);
    }

    /** Lecture « a.b.c » dans un tableau. */
    private static function dig(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
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
