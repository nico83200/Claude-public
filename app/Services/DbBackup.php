<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Sauvegarde de la base de données en fichier SQL compressé (storage/backups/db-*.sql.gz) :
 * automatique avant chaque mise à jour et chaque semaine (tâche planifiée), ou à la demande.
 * Les secrets (clés API) sont dans la table settings : le fichier est donc stocké hors du web
 * et ne se télécharge que depuis l'administration.
 */
final class DbBackup
{
    private const KEEP = 8;

    public static function dir(): string
    {
        return STORAGE_PATH . '/backups';
    }

    /** Crée une sauvegarde et renvoie son nom de fichier. */
    public static function create(string $reason = 'manuelle'): string
    {
        $pdo = Database::pdo();
        $driver = Database::driver();
        $dir = self::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'db-' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($reason)) . '.sql.gz';
        $gz = gzopen($dir . '/' . $name, 'wb6');
        if (!$gz) {
            throw new \RuntimeException('Impossible d\'écrire la sauvegarde dans storage/backups.');
        }
        gzwrite($gz, "-- Sauvegarde " . config('app.name', 'boutique') . ' — ' . date('d/m/Y H:i:s') . " ($driver, $reason)\n");
        if ($driver === 'mysql') {
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            gzwrite($gz, "PRAGMA foreign_keys = OFF;\n\n");
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
        }
        foreach ($tables as $table) {
            $quoted = $driver === 'mysql' ? '`' . str_replace('`', '``', $table) . '`' : '"' . str_replace('"', '""', $table) . '"';
            $create = $driver === 'mysql'
                ? $pdo->query("SHOW CREATE TABLE $quoted")->fetch(\PDO::FETCH_NUM)[1]
                : $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($table))->fetchColumn();
            gzwrite($gz, "DROP TABLE IF EXISTS $quoted;\n$create;\n");
            $stmt = $pdo->query("SELECT * FROM $quoted");
            $batch = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $batch[] = '(' . implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row)) . ')';
                if (count($batch) === 100) {
                    gzwrite($gz, "INSERT INTO $quoted VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO $quoted VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS = 1;\n" : "PRAGMA foreign_keys = ON;\n");
        gzclose($gz);
        self::prune();
        return $name;
    }

    /** @return list<array{name:string,size:int,date:int}> du plus récent au plus ancien */
    public static function list(): array
    {
        $files = [];
        foreach (glob(self::dir() . '/db-*.sql.gz') ?: [] as $path) {
            $files[] = ['name' => basename($path), 'size' => (int) filesize($path), 'date' => (int) filemtime($path)];
        }
        usort($files, static fn ($a, $b) => $b['date'] <=> $a['date'] ?: strcmp($b['name'], $a['name']));
        return $files;
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^db-\d{8}-\d{6}-[a-z0-9-]+\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /** Sauvegarde hebdomadaire (appelée par la tâche planifiée). */
    public static function weekly(): string
    {
        $last = self::list()[0]['date'] ?? 0;
        if ($last > time() - 7 * 86400) {
            return 'à jour (' . date('d/m', $last) . ')';
        }
        return self::create('hebdomadaire');
    }

    private static function prune(): void
    {
        foreach (array_slice(self::list(), self::KEEP) as $old) {
            @unlink(self::dir() . '/' . $old['name']);
        }
    }
}
