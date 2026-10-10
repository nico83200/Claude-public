<?php
declare(strict_types=1);

/**
 * Accès PDO + migration automatique du schéma (création des tables, ajout des colonnes manquantes).
 * Compatible SQLite (par défaut, zéro configuration) et MySQL/MariaDB (offres mutualisées classiques).
 */
final class Database
{
    private PDO $pdo;
    private string $driver;

    public function __construct(array $cfg)
    {
        $this->driver = ($cfg['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($this->driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $cfg['host'] ?? 'localhost', (int)($cfg['port'] ?? 3306), $cfg['name'] ?? '');
            $this->pdo = new PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', $opts);
        } else {
            $path = $cfg['path'] ?? (__DIR__ . '/../data/voyage.sqlite');
            $this->pdo = new PDO('sqlite:' . $path, null, null, $opts);
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $r = $fn($this);
            $this->pdo->commit();
            return $r;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* ---------------------------------------------------------------- Migration */

    public function migrate(): void
    {
        $tables = [];
        foreach (Schema::entities() as $name => $def) {
            $tables[$name] = $def['fields'];
        }
        $tables += Schema::systemTables();

        foreach ($tables as $table => $fields) {
            $this->ensureTable($table, $fields);
        }
        // Index utiles
        $this->ensureIndex('settings', 'k', true);
        $this->ensureIndex('users', 'email', true);
        $this->ensureIndex('currencies', 'code', true);
        foreach (Schema::entities() as $name => $def) {
            foreach ($def['fields'] as $col => $f) {
                if ($f['type'] === 'fk') {
                    $this->ensureIndex($name, $col);
                }
            }
        }
    }

    private function columnType(array $f): string
    {
        $my = $this->driver === 'mysql';
        return match ($f['type']) {
            'int', 'fk' => $my ? 'INT NULL' : 'INTEGER',
            'bool' => $my ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
            'float' => $my ? 'DOUBLE NULL' : 'REAL',
            'text' => $my ? 'MEDIUMTEXT NULL' : 'TEXT',
            'date' => $my ? 'DATE NULL' : 'TEXT',
            'time' => $my ? 'VARCHAR(5) NULL' : 'TEXT',
            default => $my ? 'VARCHAR(255) NULL' : 'TEXT',
        };
    }

    private function existingColumns(string $table): array
    {
        if ($this->driver === 'mysql') {
            $exists = $this->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
            if (!$exists) {
                return [];
            }
            return array_column($this->all("SHOW COLUMNS FROM `$table`"), 'Field');
        }
        return array_column($this->all("PRAGMA table_info(\"$table\")"), 'name');
    }

    private function ensureTable(string $table, array $fields): void
    {
        $existing = $this->existingColumns($table);
        $q = $this->driver === 'mysql' ? '`' : '"';
        if (!$existing) {
            $cols = [$this->driver === 'mysql'
                ? '`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
                : '"id" INTEGER PRIMARY KEY AUTOINCREMENT'];
            foreach ($fields as $col => $f) {
                $cols[] = $q . $col . $q . ' ' . $this->columnType($f);
            }
            $cols[] = "{$q}created_at{$q} " . ($this->driver === 'mysql' ? 'DATETIME NULL' : 'TEXT');
            $cols[] = "{$q}updated_at{$q} " . ($this->driver === 'mysql' ? 'DATETIME NULL' : 'TEXT');
            $suffix = $this->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
            $this->pdo->exec("CREATE TABLE {$q}{$table}{$q} (" . implode(', ', $cols) . ')' . $suffix);
            return;
        }
        foreach ($fields + ['created_at' => ['type' => 'string'], 'updated_at' => ['type' => 'string']] as $col => $f) {
            if (!in_array($col, $existing, true)) {
                $this->pdo->exec("ALTER TABLE {$q}{$table}{$q} ADD COLUMN {$q}{$col}{$q} " . $this->columnType($f));
            }
        }
    }

    private function ensureIndex(string $table, string $col, bool $unique = false): void
    {
        $name = "idx_{$table}_{$col}";
        $u = $unique ? 'UNIQUE ' : '';
        if ($this->driver === 'mysql') {
            $exists = $this->value('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$table, $name]);
            if (!$exists) {
                $len = $unique && in_array($col, ['k', 'email', 'code'], true) ? '(191)' : '';
                $this->pdo->exec("CREATE {$u}INDEX `$name` ON `$table` (`$col`$len)");
            }
        } else {
            $this->pdo->exec("CREATE {$u}INDEX IF NOT EXISTS \"$name\" ON \"$table\" (\"$col\")");
        }
    }
}
