<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Study groups. Every list is limited to AccessScope::groupIds().
 * The department of a group comes from its specialty (3NF).
 */
final class GroupRepository
{
    private const COLUMNS = "g.*, s.code AS specialty_code, s.name_ru AS specialty_name_ru, s.name_kk AS specialty_name_kk,
        s.department_id, d.name_ru AS department_name_ru, d.name_kk AS department_name_kk,
        q.code AS qualification_code, q.name_ru AS qualification_name_ru, q.name_kk AS qualification_name_kk,
        ay.title AS academic_year_title, cur.title AS curriculum_title, cur.status AS curriculum_status,
        cu.last_name AS curator_last_name, cu.first_name AS curator_first_name, cu.middle_name AS curator_middle_name,
        (SELECT COUNT(*) FROM students st WHERE st.current_group_id = g.id AND st.status = 'active') AS students_count";

    private const JOINS = ' FROM `groups` g
        JOIN specialties s ON s.id = g.specialty_id
        JOIN departments d ON d.id = s.department_id
        JOIN academic_years ay ON ay.id = g.academic_year_id
        LEFT JOIN qualifications q ON q.id = g.qualification_id
        LEFT JOIN curricula cur ON cur.id = g.curriculum_id
        LEFT JOIN teachers ct ON ct.id = g.curator_teacher_id
        LEFT JOIN users cu ON cu.id = ct.user_id';

    /**
     * @param array{q?: string, department?: ?int, specialty?: ?int, course?: ?int, year?: ?int, status?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        [$where, $params] = $this->where($filters);
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . self::JOINS . $where, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $items = Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . $where
            . " ORDER BY g.status = 'active' DESC, g.course, g.code LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /**
     * Compact list for the API.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForApi(array $filters): array
    {
        [$where, $params] = $this->where($filters);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'code' => $r['code'],
            'name' => $r['name'],
            'course' => (int) $r['course'],
            'status' => $r['status'],
            'specialty' => $r['specialty_code'],
            'students_count' => (int) $r['students_count'],
        ], Database::fetchAll('SELECT ' . self::COLUMNS . self::JOINS . $where . ' ORDER BY g.course, g.code LIMIT 500', $params));
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE g.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> current students of the group */
    public function students(int $groupId, bool $activeOnly = false): array
    {
        return Database::fetchAll(
            'SELECT s.id, s.student_number, s.status, s.funding, u.last_name, u.first_name, u.middle_name
             FROM students s JOIN users u ON u.id = s.user_id
             WHERE s.current_group_id = ?' . ($activeOnly ? " AND s.status = 'active'" : '') . '
             ORDER BY s.status = \'active\' DESC, u.last_name, u.first_name',
            [$groupId]
        );
    }

    public function departmentOfSpecialty(int $specialtyId): ?int
    {
        $id = Database::fetchColumn('SELECT department_id FROM specialties WHERE id = ?', [$specialtyId]);

        return $id === null ? null : (int) $id;
    }

    public function qualificationBelongs(int $qualificationId, int $specialtyId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM qualifications WHERE id = ? AND specialty_id = ?',
            [$qualificationId, $specialtyId]
        ) > 0;
    }

    /** Students, history and timetable rows referencing the group (a used group is archived, not deleted). */
    public function usageCount(int $id): int
    {
        return (int) Database::fetchColumn(
            'SELECT (SELECT COUNT(*) FROM students WHERE current_group_id = ?) + (SELECT COUNT(*) FROM student_group_history WHERE group_id = ?)
                  + (SELECT COUNT(*) FROM schedule_entries WHERE group_id = ?)',
            [$id, $id, $id]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        Database::execute(
            'INSERT INTO `groups` (' . implode(', ', array_map([Database::class, 'identifier'], $columns)) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($data)
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data)));
        Database::execute("UPDATE `groups` SET {$sets} WHERE id = ?", [...array_values($data), $id]);
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM `groups` WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function where(array $filters): array
    {
        [$scopeSql, $params] = AccessScope::condition('g.id', AccessScope::groupIds());
        $where = [$scopeSql];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(g.code LIKE ? OR g.name LIKE ?)';
            array_push($params, $like, $like);
        }
        $map = ['department' => 's.department_id', 'specialty' => 'g.specialty_id', 'course' => 'g.course', 'year' => 'g.academic_year_id'];
        foreach ($map as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (in_array($filters['status'] ?? '', ['active', 'graduated', 'archived', 'disbanded'], true)) {
            $where[] = 'g.status = ?';
            $params[] = $filters['status'];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }
}
