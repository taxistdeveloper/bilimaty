<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\GuardianRepository;

/**
 * Parent accounts and their links to students. A parent gets an ordinary login with a temporary
 * password (changed at first sign-in) and the "parent" role; access to a child's data is given
 * only by a link in student_guardians.
 */
final class GuardianService
{
    public const RELATIONS = ['mother', 'father', 'guardian', 'other'];
    public const MAX_PER_STUDENT = 4;

    public function __construct(private readonly GuardianRepository $guardians = new GuardianRepository())
    {
    }

    /**
     * Create a parent account and link it to the student.
     *
     * @param array<string, mixed> $student
     * @param array<string, mixed> $data last_name, first_name, middle_name, phone, email, relation, locale
     * @return array{user_id: int, login: string, password: string}
     */
    public function createAndLink(array $student, array $data): array
    {
        $this->ensureRoom($student);
        $result = Database::transaction(function () use ($student, $data): array {
            $account = (new AccountService())->create($data, 'parent');
            $this->guardians->link((int) $student['id'], $account['user_id'], (string) $data['relation'], Auth::id());

            return $account;
        });
        AuditLogger::log('guardians.created', 'students', (int) $student['id'], null, ['user_id' => $result['user_id'], 'login' => $result['login'], 'relation' => $data['relation']]);

        return $result;
    }

    /** Link an existing parent account (e.g. a second child of the same parent). @param array<string, mixed> $student */
    public function linkExisting(array $student, string $login, string $relation): void
    {
        $this->ensureRoom($student);
        $user = $this->guardians->parentByLogin(trim($login));
        if ($user === null) {
            throw new ValidationException(['parent_login' => [__('guardians.login_not_found')]]);
        }
        if (!(bool) $user['is_parent']) {
            throw new ValidationException(['parent_login' => [__('guardians.not_parent')]]);
        }
        if ($this->guardians->isGuardian((int) $user['id'], (int) $student['id'])) {
            throw new ValidationException(['parent_login' => [__('guardians.already_linked')]]);
        }
        $this->guardians->link((int) $student['id'], (int) $user['id'], $relation, Auth::id());
        AuditLogger::log('guardians.linked', 'students', (int) $student['id'], null, ['user_id' => (int) $user['id'], 'relation' => $relation]);
    }

    /** @param array<string, mixed> $student */
    public function unlink(array $student, int $userId): void
    {
        $this->guardians->unlink((int) $student['id'], $userId);
        AuditLogger::log('guardians.unlinked', 'students', (int) $student['id'], ['user_id' => $userId], null);
    }

    /** @param array<string, mixed> $student */
    private function ensureRoom(array $student): void
    {
        if ($this->guardians->count((int) $student['id']) >= self::MAX_PER_STUDENT) {
            throw new ValidationException(['parent_login' => [__('guardians.too_many', ['max' => self::MAX_PER_STUDENT])]]);
        }
    }
}
