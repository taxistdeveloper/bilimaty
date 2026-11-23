<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

/**
 * Session-based authentication and RBAC checks.
 * Roles/permissions are loaded from the DB once per request (no stale privileges).
 */
final class Auth
{
    public const SUPER_ADMIN = 'super_admin';

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @var list<string>|null */
    private static ?array $roles = null;

    /** @var list<string>|null */
    private static ?array $permissions = null;

    private static bool $resolved = false;

    /** True when the session was ended because the account changed (password reset, deactivation). */
    private static bool $revoked = false;

    /**
     * Verify credentials. Returns the user row or null. Timing is equalized for unknown logins.
     *
     * @return array<string, mixed>|null
     */
    public static function attempt(string $login, string $password): ?array
    {
        $repository = new UserRepository();
        $user = $repository->findActiveByLogin($login);

        if ($user === null) {
            // Burn comparable CPU time so response timing does not reveal which logins exist.
            password_verify($password, password_hash('collegeos-timing-guard', PASSWORD_DEFAULT));

            return null;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $repository->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user, string $ip): void
    {
        Session::regenerate();
        CSRF::regenerate();
        Session::set('auth', ['user_id' => (int) $user['id'], 'login_at' => time()]);
        (new UserRepository())->touchLogin((int) $user['id'], $ip);
        self::reset();
    }

    /** Keep the current session valid after the user changed their own password. */
    public static function refreshSession(): void
    {
        Session::regenerate();
        $auth = Session::get('auth');
        if (is_array($auth)) {
            $auth['login_at'] = time() + 1; // strictly after password_changed_at (second precision)
            Session::set('auth', $auth);
        }
        self::reset();
    }

    public static function wasRevoked(): bool
    {
        return self::$revoked;
    }

    public static function mustChangePassword(): bool
    {
        // The platform owner who entered as an administrator, and the demo "view as role" mode,
        // do not change that person's password.
        return Session::get('impersonator') === null && Session::get('demo_origin') === null && (bool) (self::user()['must_change_password'] ?? false);
    }

    public static function logout(): void
    {
        Session::invalidate();
        CSRF::regenerate();
        self::reset();
    }

    public static function id(): ?int
    {
        $auth = Session::get('auth');

        return is_array($auth) && isset($auth['user_id']) ? (int) $auth['user_id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = self::id();
        if ($id === null) {
            return null;
        }

        $user = (new UserRepository())->findActive($id);
        $loginAt = (int) (Session::get('auth')['login_at'] ?? 0);
        $passwordChangedAt = (int) ($user['password_changed_ts'] ?? 0);

        // Account deactivated/deleted, or password changed elsewhere after this session started:
        // every other session of the user is terminated on its next request.
        if ($user === null || $passwordChangedAt > $loginAt) {
            Session::invalidate();
            self::$revoked = true;

            return null;
        }
        unset($user['password_hash']);
        self::$user = $user;

        return self::$user;
    }

    /** @return list<string> role slugs */
    public static function roles(): array
    {
        if (self::$roles === null) {
            $id = self::user() !== null ? self::id() : null;
            self::$roles = $id === null ? [] : (new UserRepository())->roleSlugs($id);
        }

        return self::$roles;
    }

    /** @return list<string> permission slugs */
    public static function permissions(): array
    {
        if (self::$permissions === null) {
            $id = self::user() !== null ? self::id() : null;
            self::$permissions = $id === null ? [] : (new UserRepository())->permissionSlugs($id);
        }

        return self::$permissions;
    }

    public static function hasRole(string ...$slugs): bool
    {
        return array_intersect($slugs, self::roles()) !== [];
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::hasRole(self::SUPER_ADMIN)) {
            return true;
        }

        return in_array($permission, self::permissions(), true);
    }

    public static function canAny(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$roles = null;
        self::$permissions = null;
        self::$resolved = false;
    }
}
