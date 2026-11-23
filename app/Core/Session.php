<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened native session: strict mode, HttpOnly/SameSite cookies, idle timeout,
 * periodic ID rotation and one-request flash data.
 */
final class Session
{
    private const ROTATE_EVERY = 900; // seconds

    private static bool $expired = false;

    /** @param array{name: string, lifetime: int, path: string, save_path: ?string} $config */
    public static function start(array $config, bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) ($config['lifetime'] * 60));
        if (!empty($config['save_path']) && is_dir($config['save_path']) && is_writable($config['save_path'])) {
            session_save_path($config['save_path']);
        }

        session_name($config['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $config['path'] !== '' ? $config['path'] : '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Age flash data: what was flashed on the previous request is readable now.
        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash']['new'] = [];

        $now = time();
        $last = $_SESSION['_last_activity'] ?? null;
        if (is_int($last) && $now - $last > $config['lifetime'] * 60) {
            $hadUser = isset($_SESSION['auth']['user_id']);
            self::invalidate();
            self::$expired = $hadUser;
        }
        $_SESSION['_last_activity'] = $now;

        $created = $_SESSION['_created_at'] ?? null;
        if (!is_int($created)) {
            $_SESSION['_created_at'] = $now;
        } elseif ($now - $created > self::ROTATE_EVERY) {
            self::regenerate();
        }
    }

    public static function wasExpired(): bool
    {
        return self::$expired;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Store a value for the next request only. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash']['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash']['old'][$key] ?? $_SESSION['_flash']['new'][$key] ?? $default;
    }

    /** Remove a flashed value from the current request (e.g. after a toast was rendered). */
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = self::getFlash($key, $default);
        unset($_SESSION['_flash']['old'][$key], $_SESSION['_flash']['new'][$key]);

        return $value;
    }

    /** New session ID, data kept. Call on login and privilege change (prevents fixation). */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created_at'] = time();
    }

    /** Wipe all data and issue a new ID (logout, timeout). */
    public static function invalidate(): void
    {
        $_SESSION = [
            '_flash' => ['old' => [], 'new' => []],
        ];
        self::regenerate();
    }

    /**
     * Leave a session that belongs elsewhere (another college): a new empty session with a new ID;
     * the old session is NOT deleted, so its owner stays signed in where it belongs.
     */
    public static function detach(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(false);
        }
        $_SESSION = [
            '_flash' => ['old' => [], 'new' => []],
            '_created_at' => time(),
            '_last_activity' => time(),
        ];
    }
}
