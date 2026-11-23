<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;

/**
 * Demo / presentation mode: a super administrator can look at the system through the eyes of
 * any role, one click per role, without passwords. The session switches to a representative
 * user of that role and remembers the original account, so "back" returns to it.
 *
 * Never available when APP_ENV=production.
 */
final class DemoRoleSwitch
{
    private const ORIGIN = 'demo_origin';

    public static function enabled(): bool
    {
        if (Env::get('APP_ENV') === 'production' || !Auth::check()) {
            return false;
        }

        return self::active() || Auth::hasRole(Auth::SUPER_ADMIN);
    }

    /** True while the session is "viewing as" another user. */
    public static function active(): bool
    {
        return is_array(Session::get(self::ORIGIN));
    }

    /**
     * Every role with the user that represents it (null when no active user has the role).
     *
     * @return list<array{slug: string, name: string, user_id: int|null, user_name: string|null, current: bool}>
     */
    public static function options(): array
    {
        $current = Auth::id();
        $origin = self::originId();
        $options = [];
        foreach (Database::fetchAll('SELECT slug, name_ru, name_kk FROM roles ORDER BY id') as $role) {
            $userId = (string) $role['slug'] === Auth::SUPER_ADMIN ? ($origin ?? $current) : self::representative((string) $role['slug']);
            $user = $userId !== null ? Database::fetch('SELECT last_name, first_name, middle_name FROM users WHERE id = ?', [$userId]) : null;
            $options[] = [
                'slug' => (string) $role['slug'],
                'name' => localized($role),
                'user_id' => $userId,
                'user_name' => $user !== null ? full_name($user, true) : null,
                'current' => $userId !== null && $userId === $current,
            ];
        }

        return $options;
    }

    /** Switch the session to the representative user of the role. Returns false if there is none. */
    public static function switchTo(string $roleSlug): bool
    {
        $origin = self::originId() ?? Auth::id();
        $target = $roleSlug === Auth::SUPER_ADMIN ? $origin : self::representative($roleSlug);
        if ($target === null || $origin === null) {
            return false;
        }
        if ($target === $origin) {
            self::restore();

            return true;
        }
        $originName = self::active() ? (string) Session::get(self::ORIGIN)['name'] : full_name(Auth::user() ?? [], true);
        Session::regenerate();
        // Keep the presenter's language instead of switching to the shown user's own.
        Session::set('locale', locale());
        Session::set('auth', ['user_id' => $target, 'login_at' => time()]);
        Session::set(self::ORIGIN, ['user_id' => $origin, 'name' => $originName, 'role' => $roleSlug]);
        Auth::reset();
        AuditLogger::log('auth.demo_switch', 'users', $target, null, ['role' => $roleSlug, 'origin_user_id' => $origin], $origin);

        return true;
    }

    /** Return to the account that started the demo. */
    public static function restore(): void
    {
        $origin = self::originId();
        if ($origin === null) {
            return;
        }
        Session::regenerate();
        Session::set('auth', ['user_id' => $origin, 'login_at' => time()]);
        Session::remove(self::ORIGIN);
        Auth::reset();
    }

    /** Role being shown, for the banner. */
    public static function currentRole(): ?string
    {
        $origin = Session::get(self::ORIGIN);

        return is_array($origin) ? (string) ($origin['role'] ?? '') : null;
    }

    /** Localized name of the role being shown, for the banner. */
    public static function currentRoleName(): string
    {
        $slug = (string) self::currentRole();
        $role = Database::fetch('SELECT name_ru, name_kk FROM roles WHERE slug = ?', [$slug]);

        return $role !== null ? localized($role) : $slug;
    }

    private static function originId(): ?int
    {
        $origin = Session::get(self::ORIGIN);

        return is_array($origin) && isset($origin['user_id']) ? (int) $origin['user_id'] : null;
    }

    /**
     * The active user who shows the role best: the one with the fewest other roles, then the fewest
     * permissions in all (a teacher-curator rather than a teacher who heads a department), then the oldest.
     */
    private static function representative(string $roleSlug): ?int
    {
        // The demo showcase may pin a persona per role (settings.demo_role_users, JSON role => user id).
        $pinned = json_decode((string) Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_role_users'"), true);
        if (is_array($pinned) && isset($pinned[$roleSlug]) && Database::fetchColumn(
            'SELECT 1 FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.id = ? AND r.slug = ? AND u.is_active = 1',
            [(int) $pinned[$roleSlug], $roleSlug]
        ) !== null) {
            return (int) $pinned[$roleSlug];
        }
        $id = Database::fetchColumn(
            'SELECT u.id FROM users u
               JOIN user_roles ur ON ur.user_id = u.id
               JOIN roles r ON r.id = ur.role_id AND r.slug = ?
              WHERE u.is_active = 1
              ORDER BY (SELECT COUNT(*) FROM user_roles x WHERE x.user_id = u.id),
                       (SELECT COUNT(DISTINCT rp.permission_id) FROM user_roles x JOIN role_permissions rp ON rp.role_id = x.role_id WHERE x.user_id = u.id),
                       u.id
              LIMIT 1',
            [$roleSlug]
        );

        return $id === null ? null : (int) $id;
    }
}
