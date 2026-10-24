<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Installer
{
    /** Découpe le schéma et l'adapte au pilote (MySQL natif ou SQLite pour le dev). */
    public static function statements(string $driver): array
    {
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        if ($driver === 'sqlite') {
            $sql = str_replace('INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = str_replace(' UNSIGNED', '', $sql);
            $sql = preg_replace('/\)\s*ENGINE=[^;]+;/', ');', $sql);
        }
        return array_values(array_filter(array_map('trim', explode(';', $sql))));
    }

    public static function migrate(PDO $pdo, string $driver): void
    {
        foreach (self::statements($driver) as $statement) {
            $pdo->exec($statement);
        }
    }

    /** config/config.php ne contient que l'URL, la clé de l'application et l'accès à la base. */
    public static function writeConfig(array $config): void
    {
        $config = array_intersect_key($config, ['app' => 1, 'db' => 1]);
        $export = var_export($config, true);
        $php = "<?php\n// Généré par l'installateur le " . date('d/m/Y H:i') . ". Ne pas commiter.\n"
            . "// Les clés Stripe, Printful, e-mail… sont enregistrées en base : Administration › Intégrations.\nreturn $export;\n";
        if (file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d\'écrire config/config.php (droits d\'écriture ?)');
        }
        @chmod(CONFIG_FILE, 0640);
    }

    public static function lock(): void
    {
        file_put_contents(STORAGE_PATH . '/installed.lock', date('c'));
    }

    /** Vrai si la base contient déjà une boutique installée (table des administrateurs présente). */
    public static function isShopDatabase(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Copie de secours écrite par la version 1.2.1 (reprise par la migration 005). */
    public static function restoreConfig(PDO $pdo): array
    {
        try {
            $json = $pdo->query("SELECT value FROM settings WHERE name = '_config_backup'")->fetchColumn();
            $data = $json ? json_decode((string) $json, true) : null;
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * config/config.php présent mais storage/installed.lock perdu : si la base contient bien la boutique,
     * on recrée le verrou au lieu de relancer l'assistant.
     */
    public static function recover(): bool
    {
        if (!is_file(CONFIG_FILE)) {
            return false;
        }
        try {
            if (!self::isShopDatabase(Database::pdo())) {
                return false;
            }
            self::lock();
            log_message('install', 'Verrou d\'installation absent : recréé automatiquement.');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
