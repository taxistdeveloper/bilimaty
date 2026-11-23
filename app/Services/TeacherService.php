<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\TeacherRepository;
use App\Repositories\UserRepository;

final class TeacherService
{
    private const PERSON = ['login', 'email', 'last_name', 'first_name', 'middle_name', 'phone', 'locale'];
    private const PROFILE = ['department_id', 'employee_number', 'position', 'academic_degree', 'hire_date', 'status'];

    public function __construct(
        private readonly TeacherRepository $teachers = new TeacherRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $data validated
     * @return array{id: int, login: string, password: string}
     */
    public function create(array $data): array
    {
        $result = Database::transaction(function () use ($data): array {
            $account = (new AccountService($this->users))->create($data, 'teacher');
            $id = $this->teachers->create($account['user_id'], $this->profile($data));

            return ['id' => $id] + $account;
        });

        AuditLogger::log('teachers.created', 'teachers', $result['id'], null,
            array_intersect_key($data, array_flip([...self::PERSON, ...self::PROFILE])) + ['login' => $result['login']]);

        return ['id' => $result['id'], 'login' => $result['login'], 'password' => $result['password']];
    }

    /**
     * @param array<string, mixed> $teacher current row (TeacherRepository::find)
     * @param array<string, mixed> $data validated
     */
    public function update(array $teacher, array $data): void
    {
        $person = array_intersect_key($data, array_flip(self::PERSON));
        if (($person['login'] ?? '') === '') {
            unset($person['login']); // keep the existing login
        }
        $profile = $this->profile($data);

        Database::transaction(function () use ($teacher, $person, $profile): void {
            $this->users->update((int) $teacher['user_id'], $person);
            $this->teachers->update((int) $teacher['id'], $profile);

            // A dismissed teacher cannot sign in; re-hiring restores access.
            if ($profile['status'] === 'dismissed' && (int) $teacher['is_active'] === 1) {
                $this->users->setActive((int) $teacher['user_id'], false);
            } elseif ($teacher['status'] === 'dismissed' && $profile['status'] !== 'dismissed') {
                $this->users->setActive((int) $teacher['user_id'], true);
            }
        });

        $new = $person + $profile;
        $changed = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', array_intersect_key($teacher, $new))));
        if ($changed !== []) {
            $keys = array_flip($changed);
            AuditLogger::log('teachers.updated', 'teachers', (int) $teacher['id'], array_intersect_key($teacher, $keys), array_intersect_key($new, $keys));
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function profile(array $data): array
    {
        $profile = [];
        foreach (self::PROFILE as $key) {
            $profile[$key] = $data[$key] ?? null;
        }
        $profile['department_id'] = $profile['department_id'] !== null ? (int) $profile['department_id'] : null;
        $profile['status'] ??= 'active';

        return $profile;
    }
}
