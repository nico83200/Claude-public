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

/**
 * Base commune à plusieurs clients : chaque client a ses propres tables, préfixées par son identifiant
 * (imss_users, pins_users…). Le code écrit les noms logiques (« users ») ; ils sont préfixés ici, au dernier moment.
 */
function db_prefix(): string
{
    return (string)($GLOBALS['config']['db']['prefix'] ?? '');
}

/** Noms logiques des tables de l'application (ceux du schéma). */
function db_logical_tables(): array
{
    static $t = null;
    if ($t === null && function_exists('schema_statements')) {
        $t = [];
        foreach (schema_statements('sqlite') as $st) {
            if (preg_match('/^CREATE TABLE IF NOT EXISTS (\w+)/', $st, $m)) {
                $t[] = $m[1];
            }
        }
    }
    return $t ?? [];
}

/** Ajoute le préfixe du client aux noms de tables (et d'index) d'une requête ; inchangée sans préfixe. */
function db_sql(string $sql): string
{
    $p = db_prefix();
    if ($p === '' || !($tables = db_logical_tables())) {
        return $sql;
    }
    static $re = [];
    $names = implode('|', $tables);
    $re[$names] ??= [
        // FROM users, JOIN users, INTO users, UPDATE users (pas « ON DUPLICATE KEY UPDATE colonne »), TABLE / EXISTS users, REFERENCES users
        '/(?<!KEY )\b(FROM|JOIN|INTO|UPDATE|TABLE|EXISTS|REFERENCES)(\s+)(' . $names . ')\b/i',
        // CREATE INDEX … ON users(…), PRAGMA table_info(users)
        '/\b(ON|table_info\()(\s*)(' . $names . ')\b(?=\s*[()])/i',
        // colonnes qualifiées par le nom de la table : requests.id
        '/(?<![\w.\'"$])()()(' . $names . ')(?=\.[a-z_*])/',
    ];
    $sql = preg_replace($re[$names], '$1$2' . $p . '$3', $sql);
    // index (espace de noms commun sous SQLite)
    return preg_replace('/\b(INDEX(?:\s+IF\s+NOT\s+EXISTS)?\s+)(idx_)/i', '$1' . $p . '$2', $sql);
}

/** Exécution directe (DDL, réglages de session) avec les noms de tables du client. */
function db_exec(string $sql): int|false
{
    return db()->exec(db_sql($sql));
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare(db_sql($sql));
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
