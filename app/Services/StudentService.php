<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

/**
 * Student lifecycle: enrollment, profile changes, transfers between groups and status
 * changes. Every membership change is written to student_group_history in one transaction.
 */
final class StudentService
{
    /** Student status => end_reason of the closed membership period. */
    private const END_REASONS = [
        'academic_leave' => 'academic_leave',
        'expelled' => 'expulsion',
        'graduated' => 'graduation',
        'transferred' => 'transferred_out',
    ];

    /** Accounts of these statuses are blocked; graduates and students on leave keep read access. */
    private const BLOCKED_STATUSES = ['expelled', 'transferred'];

    private const PERSON = ['login', 'email', 'last_name', 'first_name', 'middle_name', 'phone', 'locale'];
    private const PROFILE = ['student_number', 'iin', 'birth_date', 'gender', 'enrollment_date', 'funding'];

    public function __construct(
        private readonly StudentRepository $students = new StudentRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $data validated
     * @return array{id: int, login: string, password: string}
     */
    public function enroll(array $data): array
    {
        $data['enrollment_date'] = $data['enrollment_date'] ?? date('Y-m-d');
        if (($data['student_number'] ?? '') === '' || $data['student_number'] === null) {
            $data['student_number'] = $this->students->nextStudentNumber((int) substr((string) $data['enrollment_date'], 0, 4));
        }

        $result = Database::transaction(function () use ($data): array {
            $account = (new AccountService($this->users))->create($data, 'student');
            $id = $this->students->create($account['user_id'], $this->profile($data) + ['group_id' => (int) $data['group_id']]);
            $this->students->openHistory([
                'student_id' => $id,
                'group_id' => (int) $data['group_id'],
                'start_date' => $data['enrollment_date'],
                'reason' => 'enrollment',
                'order_number' => $data['order_number'] ?? null,
                'comment' => null,
                'created_by' => Auth::id(),
            ]);

            return ['id' => $id] + $account;
        });

        AuditLogger::log('students.enrolled', 'students', $result['id'], null, [
            'login' => $result['login'],
            'student_number' => $data['student_number'],
            'group_id' => (int) $data['group_id'],
            'order_number' => $data['order_number'] ?? null,
        ]);

        return ['id' => $result['id'], 'login' => $result['login'], 'password' => $result['password']];
    }

    /**
     * @param array<string, mixed> $student current row
     * @param array<string, mixed> $data validated
     */
    public function update(array $student, array $data): void
    {
        $person = array_intersect_key($data, array_flip(self::PERSON));
        if (($person['login'] ?? '') === '') {
            unset($person['login']);
        }
        $profile = $this->profile($data);
        if ($profile['student_number'] === null) {
            unset($profile['student_number']);
        }

        Database::transaction(function () use ($student, $person, $profile): void {
            $this->users->update((int) $student['user_id'], $person);
            $this->students->update((int) $student['id'], $profile);
        });

        $new = $person + $profile;
        $changed = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', array_intersect_key($student, $new))));
        if ($changed !== []) {
            $keys = array_flip($changed);
            // IIN is personal data: the audit records only the fact of a change.
            $old = array_intersect_key($student, $keys);
            $newValues = array_intersect_key($new, $keys);
            if (isset($keys['iin'])) {
                $old['iin'] = '***';
                $newValues['iin'] = '***';
            }
            AuditLogger::log('students.updated', 'students', (int) $student['id'], $old, $newValues);
        }
    }

    /**
     * Move an active student to another group.
     *
     * @param array<string, mixed> $student
     * @param array{group_id: int, date: string, reason: string, order_number: ?string, comment: ?string} $data
     */
    public function transfer(array $student, array $data): void
    {
        if ($student['status'] !== 'active') {
            throw new ValidationException(['group_id' => [__('students.transfer_inactive')]]);
        }
        if ((int) $data['group_id'] === (int) $student['current_group_id']) {
            throw new ValidationException(['group_id' => [__('students.transfer_same_group')]]);
        }

        Database::transaction(function () use ($student, $data): void {
            $this->closeOpenPeriod((int) $student['id'], $data['date'], $data['reason'], true);
            $this->students->openHistory([
                'student_id' => (int) $student['id'],
                'group_id' => (int) $data['group_id'],
                'start_date' => $data['date'],
                'reason' => $data['reason'],
                'order_number' => $data['order_number'],
                'comment' => $data['comment'],
                'created_by' => Auth::id(),
            ]);
            $this->students->setGroupAndStatus((int) $student['id'], (int) $data['group_id'], 'active');
        });

        AuditLogger::log('students.transferred', 'students', (int) $student['id'],
            ['group_id' => (int) $student['current_group_id']],
            ['group_id' => (int) $data['group_id'], 'reason' => $data['reason'], 'date' => $data['date'], 'order_number' => $data['order_number']]
        );
    }

    /**
     * Change the student's status. Leaving (leave, expulsion, graduation, transfer out)
     * closes the open period; returning to "active" (restoration) opens a new one.
     *
     * @param array<string, mixed> $student
     * @param array{status: string, date: string, group_id: ?int, order_number: ?string, comment: ?string} $data
     */
    public function changeStatus(array $student, array $data): void
    {
        $from = (string) $student['status'];
        $to = $data['status'];
        if ($from === $to) {
            throw new ValidationException(['status' => [__('students.status_same')]]);
        }
        if ($from !== 'active' && $to !== 'active') {
            throw new ValidationException(['status' => [__('students.status_restore_first')]]);
        }

        Database::transaction(function () use ($student, $data, $from, $to): void {
            $id = (int) $student['id'];
            if ($to === 'active') {
                $groupId = (int) ($data['group_id'] ?? 0);
                if ($groupId === 0) {
                    throw new ValidationException(['group_id' => [__('validation.required', ['field' => __('fields.group_id')])]]);
                }
                $this->students->openHistory([
                    'student_id' => $id,
                    'group_id' => $groupId,
                    'start_date' => $data['date'],
                    'reason' => 'restoration',
                    'order_number' => $data['order_number'],
                    'comment' => $data['comment'],
                    'created_by' => Auth::id(),
                ]);
                $this->students->setGroupAndStatus($id, $groupId, 'active');
            } else {
                $this->closeOpenPeriod($id, $data['date'], self::END_REASONS[$to], false, $data['comment'], $data['order_number']);
                $this->students->setGroupAndStatus($id, $student['current_group_id'] !== null ? (int) $student['current_group_id'] : null, $to);
            }

            $blocked = in_array($to, self::BLOCKED_STATUSES, true);
            $wasBlocked = in_array($from, self::BLOCKED_STATUSES, true);
            if ($blocked !== $wasBlocked) {
                $this->users->setActive((int) $student['user_id'], !$blocked);
            }
        });

        AuditLogger::log('students.status_changed', 'students', (int) $student['id'],
            ['status' => $from, 'group_id' => $student['current_group_id']],
            ['status' => $to, 'date' => $data['date'], 'group_id' => $data['group_id'] ?? $student['current_group_id'], 'order_number' => $data['order_number']]
        );
    }

    /**
     * Close the current membership period. For a transfer the old period ends the day
     * before the new one starts; otherwise it ends on the given date.
     */
    private function closeOpenPeriod(int $studentId, string $date, string $endReason, bool $dayBefore, ?string $comment = null, ?string $orderNumber = null): void
    {
        $open = $this->students->openPeriod($studentId);
        if ($open === null) {
            return;
        }
        if ($date < (string) $open['start_date']) {
            throw new ValidationException(['date' => [__('students.date_before_period', ['date' => format_date((string) $open['start_date'])])]]);
        }

        $end = $date;
        if ($dayBefore) {
            $previous = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
            $end = max($previous, (string) $open['start_date']);
        }
        $this->students->closeHistory((int) $open['id'], $end, $endReason);

        if ($comment !== null || $orderNumber !== null) {
            Database::execute(
                'UPDATE student_group_history SET comment = CONCAT_WS(\' · \', NULLIF(comment, \'\'), ?, ?) WHERE id = ?',
                [$orderNumber !== null ? '№ ' . $orderNumber : null, $comment, (int) $open['id']]
            );
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
            $value = $data[$key] ?? null;
            $profile[$key] = $value === '' ? null : $value;
        }

        return $profile;
    }
}
