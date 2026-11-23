<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Roles, permissions and their links.
 */
final class RoleRepository
{
    /** @return list<array<string, mixed>> */
    public function allWithCounts(): array
    {
        return Database::fetchAll(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM user_roles ur JOIN users u ON u.id = ur.user_id
                     WHERE ur.role_id = r.id AND u.deleted_at IS NULL) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permissions_count
             FROM roles r ORDER BY r.is_system DESC, r.id'
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return Database::fetchAll('SELECT id, slug, name_ru, name_kk, is_system FROM roles ORDER BY id');
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> permissions in catalogue order (config/permissions.php) */
    public function permissions(): array
    {
        return Database::fetchAll('SELECT id, slug, module, name_ru, name_kk FROM permissions ORDER BY id');
    }

    /** @return list<int> */
    public function permissionIds(int $roleId): array
    {
        return array_map('intval', array_column(
            Database::fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]),
            'permission_id'
        ));
    }

    /** @return array<int, list<string>> role id => permission slugs */
    public function permissionSlugsByRole(): array
    {
        $map = [];
        $rows = Database::fetchAll(
            'SELECT rp.role_id, p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id'
        );
        foreach ($rows as $row) {
            $map[(int) $row['role_id']][] = (string) $row['slug'];
        }

        return $map;
    }

    /** @param list<int> $permissionIds (call inside a transaction) */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::execute('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
        foreach (array_unique($permissionIds) as $permissionId) {
            Database::execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$roleId, $permissionId]);
        }
    }

    /** @param array{slug: string, name_ru: string, name_kk: string, description: ?string} $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO roles (slug, name_ru, name_kk, description, is_system) VALUES (?, ?, ?, ?, 0)',
            [$data['slug'], $data['name_ru'], $data['name_kk'], $data['description']]
        );

        return Database::lastInsertId();
    }

    /** @param array{name_ru: string, name_kk: string, description: ?string} $data */
    public function updateNames(int $id, array $data): void
    {
        Database::execute(
            'UPDATE roles SET name_ru = ?, name_kk = ?, description = ? WHERE id = ?',
            [$data['name_ru'], $data['name_kk'], $data['description'], $id]
        );
    }

    public function usersCount(int $roleId): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM user_roles WHERE role_id = ?', [$roleId]);
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM roles WHERE id = ? AND is_system = 0', [$id]);
    }
}
