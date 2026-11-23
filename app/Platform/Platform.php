<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use PDO;

/**
 * Connections of the multi-college installation: the platform database and the database of a college.
 */
final class Platform
{
    private static ?PDO $pdo = null;

    /** @var array<string, PDO> */
    private static array $tenants = [];

    /** Connection settings of $database on the configured MySQL server. @return array<string, mixed> */
    public static function config(?string $database): array
    {
        $config = (array) Config::get('database.connections.mysql');
        if ($database !== null) {
            $config['database'] = $database;
        }

        return $config;
    }

    public static function database(): string
    {
        return (string) Config::get('tenancy.platform_database', 'bilimaty_platform');
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= Database::connect(self::config(self::database()));
    }

    /** Separate connection to a college database (statistics in the owner's panel). */
    public static function tenantPdo(string $database): PDO
    {
        return self::$tenants[$database] ??= Database::connect(self::config($database));
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$tenants = [];
    }

    /** @return array<string, mixed>|null */
    public static function collegeByCode(string $code): ?array
    {
        $st = self::pdo()->prepare('SELECT c.*, p.name AS plan_name, p.max_students FROM colleges c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.code = ?');
        $st->execute([$code]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function colleges(): array
    {
        return self::pdo()->query("SELECT * FROM colleges WHERE db_deleted_at IS NULL ORDER BY name")->fetchAll();
    }

    public static function migrator(?PDO $pdo = null): Migrator
    {
        return new Migrator($pdo ?? self::pdo(), dirname(__DIR__, 2) . '/database/platform');
    }

    public static function tenantMigrator(PDO $pdo): Migrator
    {
        return new Migrator($pdo, dirname(__DIR__, 2) . '/database/migrations');
    }

    /** Create a database if missing (server-level connection). */
    public static function createDatabase(string $name): void
    {
        $config = self::config(null);
        $server = Database::connect($config, false);
        $server->exec(sprintf('CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE %s', Database::identifier($name), $config['collation']));
    }

    public static function databaseExists(string $name): bool
    {
        $st = Database::connect(self::config(null), false)->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $st->execute([$name]);

        return $st->fetchColumn() !== false;
    }

    /**
     * Base data of a college database: roles and permissions, settings, academic year, periods,
     * assessment types (no administrator — it is created by the provisioner).
     */
    public static function seedTenant(PDO $pdo, callable $out): void
    {
        foreach (require dirname(__DIR__, 2) . '/database/seeders/DatabaseSeeder.php' as $seeder) {
            if ($seeder !== 'AdminSeeder') {
                (require dirname(__DIR__, 2) . "/database/seeders/{$seeder}.php")($pdo, $out);
            }
        }
    }
}
