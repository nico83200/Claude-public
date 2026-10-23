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

    public static function writeConfig(array $config): void
    {
        $export = var_export($config, true);
        $php = "<?php\n// Généré par l'installateur le " . date('d/m/Y H:i') . ". Ne pas commiter.\nreturn $export;\n";
        if (file_put_contents(CONFIG_FILE, $php, LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d\'écrire config/config.php (droits d\'écriture ?)');
        }
        @chmod(CONFIG_FILE, 0640);
    }

    public static function lock(): void
    {
        file_put_contents(STORAGE_PATH . '/installed.lock', date('c'));
    }
}
