<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;
use PDO;
use Throwable;

/**
 * Diagnostics for deployment.
 *
 *  schema()      — database structure: engine, charset, primary keys, indexed foreign keys, migrations.
 *  environment() — production readiness: PHP, extensions, folders, .env security settings.
 *
 * Every result line is [level, message] with level OK | WARN | FAIL.
 */
final class SystemCheck
{
    public function __construct(private readonly string $basePath)
    {
    }

    /** @return list<array{0: string, 1: string}> */
    public function schema(): array
    {
        $pdo = Database::connection();
        $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $out = [];

        $charset = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()")->fetch(PDO::FETCH_NUM);
        $out[] = [$charset[0] === 'utf8mb4' ? 'OK' : 'WARN', "Database {$db}: {$charset[0]} / {$charset[1]}"];

        $tables = $pdo->query("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_ASSOC);
        $bad = array_filter($tables, static fn (array $t): bool => $t['ENGINE'] !== 'InnoDB' || !str_starts_with((string) $t['TABLE_COLLATION'], 'utf8mb4'));
        $out[] = [$bad === [] ? 'OK' : 'FAIL', count($tables) . ' tables, InnoDB + utf8mb4' . ($bad === [] ? '' : ': wrong — ' . implode(', ', array_column($bad, 'TABLE_NAME')))];

        $noPk = $pdo->query("SELECT t.TABLE_NAME FROM information_schema.TABLES t
            LEFT JOIN information_schema.TABLE_CONSTRAINTS c ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'
            WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE' AND c.CONSTRAINT_NAME IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        $out[] = [$noPk === [] ? 'OK' : 'FAIL', 'Primary keys' . ($noPk === [] ? ' in every table' : ' missing: ' . implode(', ', $noPk))];

        // A foreign key column must be the first column of some index (joins and cascades stay fast).
        $fks = $pdo->query("SELECT k.TABLE_NAME, k.COLUMN_NAME, k.CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE k
            WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL AND k.ORDINAL_POSITION = 1")->fetchAll(PDO::FETCH_ASSOC);
        $leading = [];
        foreach ($pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND SEQ_IN_INDEX = 1")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $leading[$s['TABLE_NAME'] . '.' . $s['COLUMN_NAME']] = true;
        }
        $unindexed = array_values(array_filter(array_map(static fn (array $f): string => $f['TABLE_NAME'] . '.' . $f['COLUMN_NAME'], $fks), static fn (string $c): bool => !isset($leading[$c])));
        $out[] = [$unindexed === [] ? 'OK' : 'WARN', count($fks) . ' foreign keys' . ($unindexed === [] ? ', all indexed' : '; not leading an index: ' . implode(', ', $unindexed))];

        $checks = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()")->fetchColumn();
        $out[] = ['OK', "{$checks} CHECK constraints"];

        $migrator = new Migrator($pdo, $this->basePath . '/database/migrations');
        $pending = array_values(array_diff($migrator->files(), array_keys($migrator->ran())));
        $out[] = [$pending === [] ? 'OK' : 'FAIL', $pending === [] ? 'All ' . count($migrator->files()) . ' migrations applied' : 'Pending migrations: ' . implode(', ', $pending)];

        return $out;
    }

    /** @return list<array{0: string, 1: string}> */
    public function environment(): array
    {
        $production = Config::get('app.env') === 'production';
        $out = [];
        $out[] = [PHP_VERSION_ID >= 80300 ? 'OK' : 'FAIL', 'PHP ' . PHP_VERSION . ' (8.3+ required)'];
        foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'openssl'] as $ext) {
            $out[] = [extension_loaded($ext) ? 'OK' : 'FAIL', "Extension {$ext}"];
        }
        $out[] = [extension_loaded('Zend OPcache') && (bool) ini_get('opcache.enable') ? 'OK' : 'WARN', 'OPcache ' . (extension_loaded('Zend OPcache') && (bool) ini_get('opcache.enable') ? 'enabled' : 'disabled — enable it on the server for speed')];
        foreach (['storage/logs', 'storage/cache', 'storage/private', 'storage/exports'] as $dir) {
            $path = $this->basePath . '/' . $dir;
            $out[] = [is_dir($path) && is_writable($path) ? 'OK' : 'FAIL', "{$dir} is writable"];
        }
        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $out[] = [$upload >= 5 * 1024 * 1024 ? 'OK' : 'WARN', 'upload_max_filesize = ' . ini_get('upload_max_filesize') . ' (attachments need 5M)'];

        $out[] = [$production ? 'OK' : 'WARN', 'APP_ENV = ' . Config::get('app.env') . ($production ? '' : ' (set production on the server)')];
        $out[] = [!(bool) Config::get('app.debug') ? 'OK' : ($production ? 'FAIL' : 'WARN'), 'APP_DEBUG ' . ((bool) Config::get('app.debug') ? 'is ON — error details are shown to users' : 'is off')];
        $url = (string) Config::get('app.url', '');
        $good = str_starts_with($url, 'https://') && !str_contains($url, 'localhost') && !str_ends_with((string) parse_url($url, PHP_URL_HOST), '.local');
        $out[] = [$good ? 'OK' : ($production ? 'FAIL' : 'WARN'), "APP_URL = {$url}" . ($good ? '' : ' (QR codes on documents lead here: use the public https address)')];
        $out[] = [(bool) Config::get('app.session.secure') ? 'OK' : ($production ? 'FAIL' : 'WARN'), 'SESSION_SECURE_COOKIE ' . ((bool) Config::get('app.session.secure') ? 'on' : 'off (turn on with HTTPS)')];
        $out[] = [is_file($this->basePath . '/.htaccess') && is_file($this->basePath . '/public/.htaccess') ? 'OK' : 'WARN', '.htaccess files present (project root is closed, everything goes to public/)'];

        try {
            Database::connection()->query('SELECT 1');
            $out[] = ['OK', 'Database connection'];
            $demo = (int) Database::fetchColumn("SELECT COUNT(*) FROM settings WHERE `key` LIKE 'demo\\_%' AND `value` = '1'");
            $out[] = [$demo === 0 ? 'OK' : ($production ? 'FAIL' : 'WARN'), $demo === 0 ? 'No demo data' : 'Demo data is present — use a clean database in production'];
            $weak = (int) Database::fetchColumn("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                WHERE r.slug = 'super_admin' AND u.is_active = 1 AND u.must_change_password = 1");
            $out[] = [$weak === 0 ? 'OK' : 'WARN', $weak === 0 ? 'Super admin password was changed after setup' : 'Super admin still uses the initial password: change it'];
        } catch (Throwable $e) {
            $out[] = ['FAIL', 'Database connection: ' . $e->getMessage()];
        }

        return $out;
    }

    private static function bytes(string $value): int
    {
        $n = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
