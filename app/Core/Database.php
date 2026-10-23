<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
            $pdo = new PDO('sqlite:' . $cfg['sqlite'], null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            $cfg['name']
        );
        return new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect((array) config('db'));
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function driver(): string
    {
        return (string) config('db.driver', 'mysql');
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Les noms de colonnes proviennent toujours du code, jamais de l'utilisateur. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(', ', array_map(static fn ($c) => "$c = :set_$c", array_keys($data)));
        $bind = [];
        foreach ($data as $k => $v) {
            $bind['set_' . $k] = $v;
        }
        return self::query("UPDATE $table SET $set WHERE $where", $bind + $params)->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
