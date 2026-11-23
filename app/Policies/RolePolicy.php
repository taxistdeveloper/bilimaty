<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth;

/**
 * Record-level rules for roles and the permission matrix.
 *
 * - The super_admin role is implicit "all permissions" and cannot be edited.
 * - A non-super administrator may grant or revoke only permissions they hold
 *   themselves; other permissions of the role are left untouched.
 * - System roles and roles that still have users cannot be deleted.
 */
final class RolePolicy
{
    /** @param array<string, mixed> $role */
    public function editPermissions(array $role): bool
    {
        return Auth::can('roles.manage') && $role['slug'] !== Auth::SUPER_ADMIN;
    }

    /** @param array<string, mixed> $role */
    public function delete(array $role, int $usersCount): bool
    {
        return Auth::can('roles.manage') && (int) $role['is_system'] === 0 && $usersCount === 0;
    }

    /**
     * @param list<string> $allSlugs
     * @return list<string>
     */
    public function grantablePermissions(array $allSlugs): array
    {
        if (Auth::hasRole(Auth::SUPER_ADMIN)) {
            return $allSlugs;
        }

        return array_values(array_intersect($allSlugs, Auth::permissions()));
    }
}
