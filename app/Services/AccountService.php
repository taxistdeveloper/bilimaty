<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\UserRepository;
use RuntimeException;

/**
 * Creates the user account behind a teacher or student profile
 * (login, temporary password that must be changed, role). Call inside a transaction.
 */
final class AccountService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    /**
     * @param array<string, mixed> $person last_name, first_name, middle_name, email, phone, login?, locale?
     * @return array{user_id: int, login: string, password: string}
     */
    public function create(array $person, string $roleSlug): array
    {
        $login = trim((string) ($person['login'] ?? ''));
        if ($login === '') {
            $login = LoginGenerator::generate((string) $person['last_name'], (string) $person['first_name'], $person['middle_name'] ?? null);
        }
        $password = PasswordGenerator::generate();

        $userId = $this->users->create([
            'login' => $login,
            'email' => $person['email'] ?? null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'last_name' => $person['last_name'],
            'first_name' => $person['first_name'],
            'middle_name' => $person['middle_name'] ?? null,
            'phone' => $person['phone'] ?? null,
            'locale' => $person['locale'] ?? 'kk',
            'is_active' => 1,
        ]);

        $roleId = Database::fetchColumn('SELECT id FROM roles WHERE slug = ?', [$roleSlug]);
        if ($roleId === null) {
            throw new RuntimeException("Role [{$roleSlug}] is missing: run php cli.php db:seed.");
        }
        $this->users->syncRoles($userId, [(int) $roleId], Auth::id());

        return ['user_id' => $userId, 'login' => $login, 'password' => $password];
    }
}
