<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Migrations de base de données pour les mises à jour.
 *
 * database/schema.sql décrit toujours le schéma complet de la dernière version (installation neuve) ;
 * database/migrations/NNN_description.php fait passer une base existante d'une version à la suivante.
 * Chaque fichier retourne une fonction `function (PDO $pdo, string $driver): void`.
 */
final class Migrator
{
    public static function ensureTable(): void
    {
        Database::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)');
    }

    /** @return array<string, string> nom => chemin */
    public static function files(): array
    {
        $files = [];
        foreach (glob(BASE_PATH . '/database/migrations/*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }
        ksort($files, SORT_NATURAL);
        return $files;
    }

    public static function pending(): array
    {
        self::ensureTable();
        $applied = array_column(Database::all('SELECT name FROM migrations'), 'name');
        return array_values(array_diff(array_keys(self::files()), $applied));
    }

    /** Applique les migrations en attente, dans l'ordre ; s'arrête à la première erreur. */
    public static function run(): array
    {
        $done = [];
        $files = self::files();
        foreach (self::pending() as $name) {
            $migration = require $files[$name];
            if (!is_callable($migration)) {
                throw new \RuntimeException("Migration $name invalide : elle doit retourner une fonction.");
            }
            try {
                $migration(Database::pdo(), Database::driver());
            } catch (\Throwable $e) {
                throw new \RuntimeException("Échec de la migration $name : " . $e->getMessage(), 0, $e);
            }
            Database::insert('migrations', ['name' => $name, 'applied_at' => now()]);
            $done[] = $name;
        }
        return $done;
    }

    /** Installation neuve : schema.sql contient déjà tout, les migrations existantes sont marquées comme faites. */
    public static function markAllApplied(): void
    {
        foreach (self::pending() as $name) {
            Database::insert('migrations', ['name' => $name, 'applied_at' => now()]);
        }
    }
}
