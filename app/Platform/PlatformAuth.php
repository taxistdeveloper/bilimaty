<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Database;
use App\Core\Session;

/**
 * Owner accounts of the platform (table platform_admins in the platform database).
 * Kept apart from college users: a separate session (cookie path <base>/platform/).
 */
final class PlatformAuth
{
    private const KEY = 'platform_admin_id';

    /** @var array<string, mixed>|null */
    private static ?array $admin = null;

    /** @return array<string, mixed>|null */
    public static function attempt(string $login, string $password): ?array
    {
        $admin = Database::fetch('SELECT * FROM platform_admins WHERE login = ? AND is_active = 1', [trim($login)]);
        // Same work for unknown logins: timing does not reveal which logins exist.
        static $dummy = null;
        $hash = $admin['password_hash'] ?? ($dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT));
        if (!password_verify($password, (string) $hash) || $admin === null) {
            return null;
        }

        return $admin;
    }

    public static function login(array $admin): void
    {
        Session::regenerate();
        Session::set(self::KEY, (int) $admin['id']);
        Database::execute('UPDATE platform_admins SET last_login_at = NOW() WHERE id = ?', [$admin['id']]);
        self::$admin = null;
    }

    public static function logout(): void
    {
        Session::invalidate();
        self::$admin = null;
    }

    public static function id(): ?int
    {
        return self::user() !== null ? (int) self::$admin['id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = Session::get(self::KEY);
        if (!is_int($id)) {
            return null;
        }
        if (self::$admin === null || (int) self::$admin['id'] !== $id) {
            self::$admin = Database::fetch('SELECT * FROM platform_admins WHERE id = ? AND is_active = 1', [$id]);
            if (self::$admin === null) {
                Session::remove(self::KEY);
            }
        }

        return self::$admin;
    }

    public static function setPassword(int $id, string $password): void
    {
        Database::execute('UPDATE platform_admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
        self::$admin = null;
    }
}
