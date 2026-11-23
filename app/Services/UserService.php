<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Policies\UserPolicy;
use App\Repositories\UserRepository;

/**
 * User administration use cases. Every change is transactional and audited.
 */
final class UserService
{
    private const AUDITED_FIELDS = ['login', 'email', 'last_name', 'first_name', 'middle_name', 'phone', 'locale', 'is_active'];

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly UserPolicy $policy = new UserPolicy(),
    ) {
    }

    /**
     * @param array<string, mixed> $data validated input
     * @param list<int> $roleIds
     * @return array{id: int, password: ?string} password is returned only when it was generated
     */
    public function create(array $data, array $roleIds): array
    {
        $roleIds = $this->checkAssignable($roleIds, []);
        $password = (string) ($data['password'] ?? '');
        $generated = $password === '' ? PasswordGenerator::generate() : null;

        $id = Database::transaction(function () use ($data, $roleIds, $password, $generated): int {
            $id = $this->users->create([
                'login' => $data['login'],
                'email' => $data['email'] ?? null,
                'password_hash' => password_hash($generated ?? $password, PASSWORD_DEFAULT),
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'],
                'is_active' => 1,
            ]);
            $this->users->syncRoles($id, $roleIds, Auth::id());

            return $id;
        });

        AuditLogger::log('users.created', 'users', $id, null, $this->auditable($data) + ['role_ids' => $roleIds]);

        return ['id' => $id, 'password' => $generated];
    }

    /**
     * @param array<string, mixed> $user current row
     * @param array<string, mixed> $data validated input
     * @param list<int>|null $roleIds null = roles are not being changed
     */
    public function update(array $user, array $data, ?array $roleIds): void
    {
        $id = (int) $user['id'];
        $currentRoles = $this->users->roleIds($id);
        if ($roleIds !== null) {
            $roleIds = $this->checkAssignable($roleIds, $currentRoles);
        }

        Database::transaction(function () use ($id, $data, $roleIds): void {
            $this->users->update($id, $data);
            if ($roleIds !== null) {
                $this->users->syncRoles($id, $roleIds, Auth::id());
            }
        });

        $old = $this->auditable($user);
        $new = $this->auditable($data);
        $changedOld = array_intersect_key($old, $new);
        // Compare as strings (DB returns strings), but store the real values (null stays null).
        $changedKeys = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', $changedOld)));
        $diffNew = array_intersect_key($new, array_flip($changedKeys));
        $diffOld = array_intersect_key($changedOld, array_flip($changedKeys));
        if ($roleIds !== null && $roleIds != $currentRoles) {
            $diffOld['role_ids'] = $currentRoles;
            $diffNew['role_ids'] = $roleIds;
        }
        if ($diffNew !== []) {
            AuditLogger::log('users.updated', 'users', $id, $diffOld, $diffNew);
        }
    }

    public function setActive(int $id, bool $active): void
    {
        $this->users->setActive($id, $active);
        AuditLogger::log($active ? 'users.activated' : 'users.deactivated', 'users', $id);
    }

    /** @return string the new temporary password (must be changed at next login) */
    public function resetPassword(int $id): string
    {
        $password = PasswordGenerator::generate();
        $this->users->setPassword($id, password_hash($password, PASSWORD_DEFAULT), true);
        AuditLogger::log('users.password_reset', 'users', $id);

        return $password;
    }

    /**
     * Requested roles must be assignable by the actor; roles the actor cannot manage
     * keep their current state (neither granted nor revoked).
     *
     * @param list<int> $requested
     * @param list<int> $current
     * @return list<int>
     */
    private function checkAssignable(array $requested, array $current): array
    {
        $assignable = $this->policy->assignableRoleIds();
        $requested = array_values(array_unique(array_map('intval', $requested)));

        $forbidden = array_diff($requested, $assignable, $current);
        if ($forbidden !== []) {
            throw new ValidationException(['roles' => [__('users.role_not_assignable')]]);
        }

        $kept = array_diff($current, $assignable);
        $result = array_values(array_unique(array_merge(array_intersect($requested, $assignable), $kept)));
        sort($result);

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function auditable(array $row): array
    {
        return array_intersect_key($row, array_flip(self::AUDITED_FIELDS));
    }
}
