<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Users: authentication lookups, administration queries and role assignments.
 */
final class UserRepository
{
    /** Columns safe to show in administration screens (never the password hash). */
    private const PUBLIC_COLUMNS = 'u.id, u.login, u.email, u.last_name, u.first_name, u.middle_name, u.phone,
        u.locale, u.is_active, u.last_login_at, u.last_login_ip, u.password_changed_at,
        u.must_change_password, u.created_at, u.updated_at';

    /** @return array<string, mixed>|null */
    public function findActiveByLogin(string $login): ?array
    {
        // Login may be the username or the e-mail address.
        return Database::fetch(
            'SELECT * FROM users
             WHERE (login = :login OR email = :email) AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1',
            ['login' => $login, 'email' => $login]
        );
    }

    /** @return array<string, mixed>|null */
    public function findActive(int $id): ?array
    {
        return Database::fetch(
            // UNIX_TIMESTAMP is computed by MySQL itself, so it is immune to PHP/MySQL time zone drift.
            'SELECT *, UNIX_TIMESTAMP(password_changed_at) AS password_changed_ts
             FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    /** Any non-deleted user (active or not), without the password hash. @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM users u WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    public function passwordHash(int $id): ?string
    {
        $hash = Database::fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$id]);

        return is_string($hash) ? $hash : null;
    }

    /**
     * Server-side filtered pagination for the users list.
     *
     * @param array{q?: string, role?: int|null, status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 20): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.login LIKE ? OR u.email LIKE ? OR CONCAT_WS(\' \', u.last_name, u.first_name, u.middle_name) LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles fr WHERE fr.user_id = u.id AND fr.role_id = ?)';
            $params[] = (int) $filters['role'];
        }
        $status = $filters['status'] ?? '';
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'u.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*) FROM users u' . $whereSql, $params);
        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $items = Database::fetchAll(
            'SELECT ' . self::PUBLIC_COLUMNS . ',
                    GROUP_CONCAT(r.slug ORDER BY r.id SEPARATOR \',\') AS role_slugs
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id'
            . $whereSql .
            ' GROUP BY u.id
             ORDER BY u.last_name, u.first_name, u.id
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO users (login, email, password_hash, last_name, first_name, middle_name, phone, locale,
                                is_active, password_changed_at, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)',
            [
                $data['login'], $data['email'], $data['password_hash'], $data['last_name'], $data['first_name'],
                $data['middle_name'], $data['phone'], $data['locale'], (int) $data['is_active'],
            ]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data profile fields only */
    public function update(int $id, array $data): void
    {
        $allowed = ['login', 'email', 'last_name', 'first_name', 'middle_name', 'phone', 'locale'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data)));
        Database::execute("UPDATE users SET {$sets} WHERE id = ?", [...array_values($data), $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        Database::execute('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    /** Changing the password also terminates the user's other sessions (see Auth::user()). */
    public function setPassword(int $id, string $hash, bool $mustChange): void
    {
        Database::execute(
            'UPDATE users SET password_hash = ?, password_changed_at = NOW(), must_change_password = ? WHERE id = ?',
            [$hash, $mustChange ? 1 : 0, $id]
        );
    }

    public function updatePasswordHash(int $userId, string $hash): void
    {
        Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
    }

    public function touchLogin(int $userId, string $ip): void
    {
        Database::execute('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [$ip, $userId]);
    }

    public function updateLocale(int $userId, string $locale): void
    {
        Database::execute('UPDATE users SET locale = ? WHERE id = ?', [$locale, $userId]);
    }

    /** @return list<string> */
    public function roleSlugs(int $userId): array
    {
        return array_column(Database::fetchAll(
            'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.id',
            [$userId]
        ), 'slug');
    }

    /** @return list<int> */
    public function roleIds(int $userId): array
    {
        return array_map('intval', array_column(
            Database::fetchAll('SELECT role_id FROM user_roles WHERE user_id = ? ORDER BY role_id', [$userId]),
            'role_id'
        ));
    }

    /** @return list<array<string, mixed>> */
    public function roles(int $userId): array
    {
        return Database::fetchAll(
            'SELECT r.id, r.slug, r.name_ru, r.name_kk FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.id',
            [$userId]
        );
    }

    /** @return list<string> */
    public function permissionSlugs(int $userId): array
    {
        return array_column(Database::fetchAll(
            'SELECT DISTINCT p.slug FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?',
            [$userId]
        ), 'slug');
    }

    /**
     * Replace the user's roles with $roleIds (call inside a transaction).
     *
     * @param list<int> $roleIds
     */
    public function syncRoles(int $userId, array $roleIds, ?int $assignedBy): void
    {
        $current = $this->roleIds($userId);
        $remove = array_values(array_diff($current, $roleIds));
        $add = array_values(array_diff($roleIds, $current));

        if ($remove !== []) {
            Database::execute(
                'DELETE FROM user_roles WHERE user_id = ? AND role_id IN (' . implode(',', array_fill(0, count($remove), '?')) . ')',
                [$userId, ...$remove]
            );
        }
        foreach ($add as $roleId) {
            Database::execute(
                'INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)',
                [$userId, $roleId, $assignedBy]
            );
        }
    }
}
