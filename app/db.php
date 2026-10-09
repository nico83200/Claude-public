<?php
declare(strict_types=1);

/**
 * Accès base de données (PDO) — compatible MySQL/MariaDB et SQLite.
 */
function db(): PDO
{
    if (isset($GLOBALS['db_pdo'])) {
        return $GLOBALS['db_pdo'];
    }
    $c = $GLOBALS['config']['db'];
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'] ?? 'localhost',
            (int)($c['port'] ?? 3306),
            $c['name']
        );
        $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', $opts);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $GLOBALS['db_pdo'] = $pdo;
}

/** Ferme la connexion (changement de client par la console multi-clients). */
function db_reset(): void
{
    unset($GLOBALS['db_pdo']);
}

function db_driver(): string
{
    return ($GLOBALS['config']['db']['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $params = []): int
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    return q("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
}

/** Construit "(?,?,?)" pour une clause IN. */
function in_list(array $values): string
{
    return $values ? '(' . implode(',', array_fill(0, count($values), '?')) . ')' : '(NULL)';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function tx(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) { // transaction déjà ouverte : on s'y inscrit
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
