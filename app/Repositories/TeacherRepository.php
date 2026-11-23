<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Teachers with their user accounts. Lists are limited to the user's department scope.
 */
final class TeacherRepository
{
    private const COLUMNS = 't.id, t.user_id, t.department_id, t.employee_number, t.position, t.academic_degree,
        t.hire_date, t.status, u.login, u.email, u.phone, u.last_name, u.first_name, u.middle_name, u.locale,
        u.is_active, u.last_login_at, d.name_ru AS department_name_ru, d.name_kk AS department_name_kk';

    /**
     * @param array{q?: string, department?: ?int, status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        [$scopeSql, $params] = AccessScope::condition('t.department_id', AccessScope::departmentIds());
        $where = ['u.deleted_at IS NULL', $scopeSql];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = "(CONCAT_WS(' ', u.last_name, u.first_name, u.middle_name) LIKE ? OR u.login LIKE ? OR t.employee_number LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['department'])) {
            $where[] = 't.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (in_array($filters['status'] ?? '', ['active', 'on_leave', 'dismissed'], true)) {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        }

        $from = ' FROM teachers t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id
                  WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . $from, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $items = Database::fetchAll(
            'SELECT ' . self::COLUMNS . ',
                (SELECT GROUP_CONCAT(g.code ORDER BY g.code SEPARATOR \', \') FROM `groups` g
                 WHERE g.curator_teacher_id = t.id AND g.status = \'active\') AS curated_groups'
            . $from . ' ORDER BY u.last_name, u.first_name LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT ' . self::COLUMNS . ' FROM teachers t JOIN users u ON u.id = t.user_id
             LEFT JOIN departments d ON d.id = t.department_id WHERE t.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function curatedGroups(int $teacherId): array
    {
        return Database::fetchAll(
            'SELECT g.id, g.code, g.name, g.course, g.status,
                    (SELECT COUNT(*) FROM students s WHERE s.current_group_id = g.id AND s.status = \'active\') AS students_count
             FROM `groups` g WHERE g.curator_teacher_id = ? ORDER BY g.status, g.code',
            [$teacherId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function headedDepartments(int $teacherId): array
    {
        return Database::fetchAll('SELECT id, code, name_ru, name_kk FROM departments WHERE head_teacher_id = ?', [$teacherId]);
    }

    /** @param array<string, mixed> $data */
    public function create(int $userId, array $data): int
    {
        Database::execute(
            'INSERT INTO teachers (user_id, department_id, employee_number, position, academic_degree, hire_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $data['department_id'], $data['employee_number'], $data['position'], $data['academic_degree'], $data['hire_date'], $data['status']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE teachers SET department_id = ?, employee_number = ?, position = ?, academic_degree = ?, hire_date = ?, status = ?
             WHERE id = ?',
            [$data['department_id'], $data['employee_number'], $data['position'], $data['academic_degree'], $data['hire_date'], $data['status'], $id]
        );
    }
}
