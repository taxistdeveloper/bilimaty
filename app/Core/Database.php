<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Single shared PDO connection. All queries go through prepared statements.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(Config::get('database.connections.mysql'));
        }

        return self::$pdo;
    }

    /**
     * @param array<string, mixed> $config
     * @param bool $withDatabase false = connect to the server only (used by db:create)
     */
    public static function connect(array $config, bool $withDatabase = true): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['charset']
        );
        if ($withDatabase) {
            $dsn .= ';dbname=' . $config['database'];
        }

        try {
            $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $pdo->exec("SET NAMES {$config['charset']} COLLATE {$config['collation']}");
            $pdo->prepare('SET time_zone = ?')->execute([$config['timezone']]);
        } catch (PDOException $e) {
            // Never leak host/user/password to the client: the handler logs the previous exception.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return $pdo;
    }

    /** @param array<int|string, mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @param array<int|string, mixed> $params */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<int|string, mixed> $params */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::connection()->lastInsertId();
    }

    /**
     * Runs the callback inside a transaction; nested calls reuse the outer transaction.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Quote a SQL identifier (table/column) after strict whitelist validation. */
    public static function identifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new RuntimeException('Invalid SQL identifier.');
        }

        return '`' . $name . '`';
    }

    /** For tests and the CLI. */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
