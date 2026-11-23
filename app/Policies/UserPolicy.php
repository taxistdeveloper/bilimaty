<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Auth;
use App\Core\Config;
use App\Repositories\RoleRepository;

/**
 * Record-level rules for user administration.
 *
 * - Only a Super Admin may manage Super Admin accounts or grant that role.
 * - Nobody changes their own roles or status, or resets their own password here
 *   (prevents lock-outs and self-escalation; own password is changed in the profile).
 * - A role can be granted only if the actor holds every administrative permission
 *   of that role (config permissions.administrative): no privilege escalation
 *   through role assignment, while teaching/student roles stay assignable.
 */
final class UserPolicy
{
    public function __construct(private readonly RoleRepository $roles = new RoleRepository())
    {
    }

    /** @param list<string> $targetRoleSlugs */
    public function manage(array $targetRoleSlugs): bool
    {
        if (!Auth::can('users.manage')) {
            return false;
        }

        return !in_array(Auth::SUPER_ADMIN, $targetRoleSlugs, true) || Auth::hasRole(Auth::SUPER_ADMIN);
    }

    /** @param list<string> $targetRoleSlugs */
    public function changeRoles(int $targetId, array $targetRoleSlugs): bool
    {
        return $targetId !== Auth::id() && $this->manage($targetRoleSlugs);
    }

    /** @param list<string> $targetRoleSlugs */
    public function changeStatus(int $targetId, array $targetRoleSlugs): bool
    {
        return $targetId !== Auth::id() && $this->manage($targetRoleSlugs);
    }

    /** @param list<string> $targetRoleSlugs */
    public function resetPassword(int $targetId, array $targetRoleSlugs): bool
    {
        return $targetId !== Auth::id() && $this->manage($targetRoleSlugs);
    }

    /** @return list<int> ids of roles the current user may grant or revoke */
    public function assignableRoleIds(): array
    {
        $roles = $this->roles->all();
        if (Auth::hasRole(Auth::SUPER_ADMIN)) {
            return array_map(static fn (array $r): int => (int) $r['id'], $roles);
        }

        $own = Auth::permissions();
        $administrative = (array) Config::get('permissions.administrative', []);
        $byRole = $this->roles->permissionSlugsByRole();
        $ids = [];
        foreach ($roles as $role) {
            if ($role['slug'] === Auth::SUPER_ADMIN) {
                continue;
            }
            $adminPermissions = array_intersect($byRole[(int) $role['id']] ?? [], $administrative);
            if (array_diff($adminPermissions, $own) === []) {
                $ids[] = (int) $role['id'];
            }
        }

        return $ids;
    }
}
