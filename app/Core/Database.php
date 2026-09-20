<?php
namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper. One connection per request, prepared statements only.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = Config::get('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            if (Config::get('app.debug')) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
            }
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return self::$pdo;
    }

    /** Run a query and return all rows. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Run a query and return the first row, or null. */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Return a single scalar value from the first column of the first row. */
    public static function scalar(string $sql, array $params = [], mixed $default = null): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    }

    /** Execute a write and return the number of affected rows. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Insert and return the new primary key. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn($c) => "`$c`", $cols)),
            implode(', ', array_map(static fn($c) => ":$c", $cols))
        );
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($data);
        return (int) self::pdo()->lastInsertId();
    }

    /** Update rows matching a simple equality WHERE clause. */
    public static function update(string $table, array $data, array $where): int
    {
        $set    = implode(', ', array_map(static fn($c) => "`$c` = :set_$c", array_keys($data)));
        $cond   = implode(' AND ', array_map(static fn($c) => "`$c` = :whr_$c", array_keys($where)));
        $params = [];
        foreach ($data as $k => $v)  { $params["set_$k"] = $v; }
        foreach ($where as $k => $v) { $params["whr_$k"] = $v; }

        $stmt = self::pdo()->prepare("UPDATE `$table` SET $set WHERE $cond");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $cond   = implode(' AND ', array_map(static fn($c) => "`$c` = :$c", array_keys($where)));
        $stmt   = self::pdo()->prepare("DELETE FROM `$table` WHERE $cond");
        $stmt->execute($where);
        return $stmt->rowCount();
    }

    public static function beginTransaction(): void
    {
        if (!self::pdo()->inTransaction()) {
            self::pdo()->beginTransaction();
        }
    }

    public static function commit(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollBack(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /** Run a closure inside a transaction, rolling back on any exception. */
    public static function transaction(callable $fn): mixed
    {
        self::beginTransaction();
        try {
            $result = $fn();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
