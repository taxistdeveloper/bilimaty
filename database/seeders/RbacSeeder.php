<?php

declare(strict_types=1);

/**
 * Syncs roles, permissions and role_permissions with config/permissions.php.
 * Idempotent: safe to run on every deploy. Grants are replaced for system roles.
 */
return static function (PDO $pdo, callable $out): void {
    $catalogue = require dirname(__DIR__, 2) . '/config/permissions.php';

    $pdo->beginTransaction();
    try {
        $upsertPermission = $pdo->prepare(
            'INSERT INTO permissions (slug, module, name_ru, name_kk) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE module = VALUES(module), name_ru = VALUES(name_ru), name_kk = VALUES(name_kk)'
        );
        foreach ($catalogue['permissions'] as $slug => [$module, $ru, $kk]) {
            $upsertPermission->execute([$slug, $module, $ru, $kk]);
        }

        $upsertRole = $pdo->prepare(
            'INSERT INTO roles (slug, name_ru, name_kk, is_system) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name_ru = VALUES(name_ru), name_kk = VALUES(name_kk), is_system = 1'
        );
        $permissionIds = $pdo->query('SELECT slug, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);
        $roleId = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
        $clear = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
        $grant = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

        foreach ($catalogue['roles'] as $slug => [$ru, $kk, $permissions]) {
            $upsertRole->execute([$slug, $ru, $kk]);
            $roleId->execute([$slug]);
            $id = (int) $roleId->fetchColumn();

            $clear->execute([$id]);
            foreach ($permissions as $permission) {
                if (!isset($permissionIds[$permission])) {
                    throw new RuntimeException("Unknown permission [{$permission}] for role [{$slug}].");
                }
                $grant->execute([$id, $permissionIds[$permission]]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $out(sprintf('RBAC: %d roles, %d permissions.', count($catalogue['roles']), count($catalogue['permissions'])));
};
