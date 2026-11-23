<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Students, their accounts and group membership history.
 * Lists are limited to AccessScope::groupIds() (by the student's current group).
 */
final class StudentRepository
{
    private const COLUMNS = 's.*, u.login, u.email, u.phone, u.last_name, u.first_name, u.middle_name, u.locale, u.is_active,
        u.last_login_at, g.code AS group_code, g.name AS group_name, g.course, g.status AS group_status,
        sp.code AS specialty_code, sp.name_ru AS specialty_name_ru, sp.name_kk AS specialty_name_kk,
        sp.department_id, d.name_ru AS department_name_ru, d.name_kk AS department_name_kk,
        q.name_ru AS qualification_name_ru, q.name_kk AS qualification_name_kk';

    private const JOINS = ' FROM students s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN `groups` g ON g.id = s.current_group_id
        LEFT JOIN specialties sp ON sp.id = g.specialty_id
        LEFT JOIN departments d ON d.id = sp.department_id
        LEFT JOIN qualifications q ON q.id = g.qualification_id';

    /**
     * @param array{q?: string, group?: ?int, department?: ?int, course?: ?int, status?: string, search_iin?: bool} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        [$scopeSql, $params] = AccessScope::condition('s.current_group_id', AccessScope::groupIds());
        $where = ['u.deleted_at IS NULL', $scopeSql];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $parts = ["CONCAT_WS(' ', u.last_name, u.first_name, u.middle_name) LIKE ?", 's.student_number LIKE ?', 'u.login LIKE ?'];
            array_push($params, $like, $like, $like);
            // Searching by IIN (personal data) only for users who manage students.
            if (!empty($filters['search_iin']) && preg_match('/^\d{4,12}$/', $q)) {
                $parts[] = 's.iin LIKE ?';
                $params[] = $q . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        $map = ['group' => 's.current_group_id', 'department' => 'sp.department_id', 'course' => 'g.course'];
        foreach ($map as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (in_array($filters['status'] ?? '', ['active', 'academic_leave', 'expelled', 'graduated', 'transferred'], true)) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . self::JOINS . $whereSql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $items = Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . $whereSql
            . " ORDER BY s.status = 'active' DESC, u.last_name, u.first_name LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE s.id = ? AND u.deleted_at IS NULL', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT h.*, g.code AS group_code, a.last_name AS author_last_name, a.first_name AS author_first_name,
                    a.middle_name AS author_middle_name
             FROM student_group_history h
             JOIN `groups` g ON g.id = h.group_id
             LEFT JOIN users a ON a.id = h.created_by
             WHERE h.student_id = ? ORDER BY h.start_date DESC, h.id DESC',
            [$studentId]
        );
    }

    /** @return array<string, mixed>|null the open (current) membership period */
    public function openPeriod(int $studentId): ?array
    {
        return Database::fetch(
            'SELECT * FROM student_group_history WHERE student_id = ? AND end_date IS NULL ORDER BY start_date DESC, id DESC LIMIT 1',
            [$studentId]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): int
    {
        \App\Platform\Limits::assertCanAddStudent();
        Database::execute(
            'INSERT INTO students (user_id, student_number, iin, birth_date, gender, current_group_id, enrollment_date, funding, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'active\')',
            [$userId, $data['student_number'], $data['iin'], $data['birth_date'], $data['gender'], $data['group_id'], $data['enrollment_date'], $data['funding']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data profile fields */
    public function update(int $id, array $data): void
    {
        $allowed = ['student_number', 'iin', 'birth_date', 'gender', 'enrollment_date', 'funding'];
        $data = array_intersect_key($data, array_flip($allowed));
        $sets = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data)));
        Database::execute("UPDATE students SET {$sets} WHERE id = ?", [...array_values($data), $id]);
    }

    public function setGroupAndStatus(int $id, ?int $groupId, string $status): void
    {
        Database::execute('UPDATE students SET current_group_id = ?, status = ? WHERE id = ?', [$groupId, $status, $id]);
    }

    /** @param array<string, mixed> $row */
    public function openHistory(array $row): void
    {
        Database::execute(
            'INSERT INTO student_group_history (student_id, group_id, start_date, reason, order_number, comment, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$row['student_id'], $row['group_id'], $row['start_date'], $row['reason'], $row['order_number'], $row['comment'], $row['created_by']]
        );
    }

    public function closeHistory(int $historyId, string $endDate, string $endReason): void
    {
        Database::execute(
            'UPDATE student_group_history SET end_date = ?, end_reason = ? WHERE id = ?',
            [$endDate, $endReason, $historyId]
        );
    }

    public function nextStudentNumber(int $year): string
    {
        $prefix = (string) $year;
        $last = Database::fetchColumn(
            'SELECT MAX(student_number) FROM students WHERE student_number LIKE ? AND CHAR_LENGTH(student_number) = 9',
            [$prefix . '%']
        );
        $sequence = $last !== null && ctype_digit((string) $last) ? (int) substr((string) $last, 4) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function groupDepartment(int $groupId): ?int
    {
        $id = Database::fetchColumn('SELECT s.department_id FROM `groups` g JOIN specialties s ON s.id = g.specialty_id WHERE g.id = ?', [$groupId]);

        return $id === null ? null : (int) $id;
    }

    public function groupIsActive(int $groupId): bool
    {
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM `groups` WHERE id = ? AND status = 'active'", [$groupId]) > 0;
    }
}
