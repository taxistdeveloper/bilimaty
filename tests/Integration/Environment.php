<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use RuntimeException;

/**
 * Prepares an isolated environment for integration tests: a separate "_test" database
 * (dropped, migrated and seeded with demo data) and the PHP built-in server for HTTP tests.
 */
final class Environment
{
    public const PASSWORD = 'Test2026pass!';

    /** @var resource|null */
    private static $server = null;
    private static string $baseUrl = '';
    /** @var array<string, string> */
    private static array $db = [];

    public static function prepare(string $root): void
    {
        $env = self::readEnv($root . '/.env');
        $get = static fn (string $key, string $default = ''): string => (string) (getenv('TEST_' . $key) ?: ($env[$key] ?? $default));
        self::$db = [
            'host' => $get('DB_HOST', '127.0.0.1'),
            'port' => $get('DB_PORT', '3306'),
            'database' => (string) (getenv('TEST_DB_DATABASE') ?: ($env['DB_DATABASE'] ?? 'collegeos') . '_test'),
            'username' => $get('DB_USERNAME', 'root'),
            'password' => getenv('TEST_DB_PASSWORD') !== false ? (string) getenv('TEST_DB_PASSWORD') : ($env['DB_PASSWORD'] ?? ''),
        ];
        if (!preg_match('/^[A-Za-z0-9_]+_test$/', self::$db['database'])) {
            throw new RuntimeException('The test database name must end with "_test" (got "' . self::$db['database'] . '").');
        }
        // The application (CLI and server) reads these instead of the working database.
        foreach (['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'] as $key => $field) {
            putenv($key . '=' . self::$db[$field]);
        }
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=false');
        putenv('APP_URL=');
        putenv('TENANCY=false'); // single-college tests; TenancyTest starts its own multi-college server
        putenv('SEED_ADMIN_LOGIN=admin');
        putenv('SEED_ADMIN_PASSWORD=' . self::PASSWORD);

        echo 'Test database: ' . self::$db['database'] . ' on ' . self::$db['host'] . ':' . self::$db['port'] . "\n";
        $pdo = new PDO('mysql:host=' . self::$db['host'] . ';port=' . self::$db['port'] . ';charset=utf8mb4', self::$db['username'], self::$db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP DATABASE IF EXISTS `' . self::$db['database'] . '`');
        $pdo->exec('CREATE DATABASE `' . self::$db['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        foreach (['migrate', 'db:seed', 'db:seed --demo'] as $command) {
            exec(PHP_BINARY . ' ' . escapeshellarg($root . '/cli.php') . ' ' . $command . ' 2>&1', $output, $code);
            if ($code !== 0) {
                throw new RuntimeException("cli.php {$command} failed:\n" . implode("\n", array_slice($output, -10)));
            }
        }

        $port = self::freePort();
        self::$baseUrl = 'http://127.0.0.1:' . $port;
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            $root
        );
        for ($i = 0; $i < 50; $i++) {
            if (@file_get_contents(self::$baseUrl . '/login') !== false) {
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('The test server did not start on port ' . $port);
    }

    /** @var list<resource> extra servers started by tests */
    private static array $extra = [];

    /**
     * Another built-in server of the same code with extra environment (e.g. TENANCY=true).
     *
     * @param array<string, string> $env
     */
    public static function serve(string $root, array $env): string
    {
        $port = self::freePort();
        $url = 'http://127.0.0.1:' . $port;
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $env = array_merge(getenv(), $env);
        $command = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'];
        // proc_open drops variables with empty values (e.g. DB_PASSWORD=): pass them through env(1).
        $empty = array_keys(array_filter($env, static fn ($v): bool => $v === ''));
        if ($empty !== [] && PHP_OS_FAMILY !== 'Windows') {
            $command = array_merge(['/usr/bin/env'], array_map(static fn (string $k): string => $k . '=', $empty), $command);
        }
        self::$extra[] = proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, $root, $env);
        for ($i = 0; $i < 50; $i++) {
            if (@file_get_contents($url . '/') !== false || str_contains((string) ($http_response_header[0] ?? ''), 'HTTP')) {
                return $url;
            }
            usleep(100_000);
        }
        throw new RuntimeException('The extra test server did not start on port ' . $port);
    }

    /** Server-level connection (create/drop databases of the multi-college test). */
    public static function server(): PDO
    {
        return new PDO('mysql:host=' . self::$db['host'] . ';port=' . self::$db['port'] . ';charset=utf8mb4', self::$db['username'], self::$db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    /** @return array<string, string> connection settings of the test database */
    public static function db(): array
    {
        return self::$db;
    }

    public static function baseUrl(): string
    {
        return self::$baseUrl;
    }

    public static function pdo(): PDO
    {
        static $pdo = null;

        return $pdo ??= new PDO(
            'mysql:host=' . self::$db['host'] . ';port=' . self::$db['port'] . ';dbname=' . self::$db['database'] . ';charset=utf8mb4',
            self::$db['username'],
            self::$db['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    public static function shutdown(): void
    {
        foreach (self::$extra as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) stream_socket_get_name($socket, false), strrpos((string) stream_socket_get_name($socket, false), ':') + 1);
        fclose($socket);

        return $port;
    }

    /** @return array<string, string> */
    private static function readEnv(string $file): array
    {
        $out = [];
        foreach (is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
            if (str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $out[trim($k)] = trim(trim($v), "\"'");
        }

        return $out;
    }
}
