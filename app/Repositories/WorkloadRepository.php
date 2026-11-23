<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Workload plans, distributed hours (assignments), manual actual hours and the journal facts.
 */
final class WorkloadRepository
{
    private const PLAN_COLUMNS = "p.*, y.title AS year_title, y.start_date AS year_start, y.end_date AS year_end, y.status AS year_status,
        t.department_id, u.last_name, u.first_name, u.middle_name, t.position";

    private const ASSIGNMENT_COLUMNS = "wa.*, p.teacher_id, p.academic_year_id, p.status AS plan_status, p.max_hours,
        s.number AS semester_number, s.start_date AS semester_start, s.end_date AS semester_end,
        g.code AS group_code, sb.code AS subject_code, sb.name_ru AS subject_name_ru, sb.name_kk AS subject_name_kk,
        wt.code AS type_code, wt.name_ru AS type_name_ru, wt.name_kk AS type_name_kk, wt.lesson_type, wt.curriculum_field,
        y.status AS year_status";

    private const ASSIGNMENT_JOINS = ' FROM workload_assignments wa
        JOIN workload_plans p ON p.id = wa.plan_id
        JOIN academic_years y ON y.id = p.academic_year_id
        JOIN semesters s ON s.id = wa.semester_id
        JOIN workload_types wt ON wt.id = wa.workload_type_id
        LEFT JOIN `groups` g ON g.id = wa.group_id
        LEFT JOIN subjects sb ON sb.id = wa.subject_id';

    // ---- types ---------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function types(bool $activeOnly = true): array
    {
        return Database::fetchAll('SELECT * FROM workload_types' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
    }

    /** @return array<string, mixed>|null */
    public function type(int $id): ?array
    {
        return Database::fetch('SELECT * FROM workload_types WHERE id = ?', [$id]);
    }

    // ---- plans ---------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function plan(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::PLAN_COLUMNS . ' FROM workload_plans p JOIN academic_years y ON y.id = p.academic_year_id
            JOIN teachers t ON t.id = p.teacher_id JOIN users u ON u.id = t.user_id WHERE p.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function planFor(int $yearId, int $teacherId): ?array
    {
        $id = Database::fetchColumn('SELECT id FROM workload_plans WHERE academic_year_id = ? AND teacher_id = ?', [$yearId, $teacherId]);

        return $id === null ? null : $this->plan((int) $id);
    }

    /** @param array<string, mixed> $data */
    public function createPlan(array $data): int
    {
        Database::execute(
            'INSERT INTO workload_plans (academic_year_id, teacher_id, rate, max_hours, note, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['academic_year_id'], $data['teacher_id'], $data['rate'], $data['max_hours'], $data['note'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updatePlan(int $id, array $data): void
    {
        Database::execute('UPDATE workload_plans SET rate = ?, max_hours = ?, note = ? WHERE id = ?', [$data['rate'], $data['max_hours'], $data['note'], $id]);
    }

    public function setPlanStatus(int $id, string $status, ?int $userId): void
    {
        Database::execute(
            'UPDATE workload_plans SET status = ?, approved_at = ' . ($status === 'approved' ? 'NOW()' : 'NULL') . ', approved_by = ? WHERE id = ?',
            [$status, $status === 'approved' ? $userId : null, $id]
        );
    }

    /**
     * Teachers in scope with their plan header for the year (plan columns are NULL when absent).
     *
     * @param list<int>|null $departments null = all
     * @return list<array<string, mixed>>
     */
    public function teachers(int $yearId, ?array $departments, ?int $selfId, ?int $departmentFilter): array
    {
        $where = ["t.status <> 'dismissed'", 'u.deleted_at IS NULL'];
        $params = [$yearId];
        if ($departments !== null) {
            $own = $departments === [] ? '1 = 0' : 't.department_id IN (' . implode(',', array_fill(0, count($departments), '?')) . ')';
            $params = array_merge($params, $departments);
            if ($selfId !== null) {
                $own = "({$own} OR t.id = ?)";
                $params[] = $selfId;
            }
            $where[] = $own;
        }
        if ($departmentFilter !== null) {
            $where[] = 't.department_id = ?';
            $params[] = $departmentFilter;
        }

        return Database::fetchAll(
            'SELECT t.id AS teacher_id, t.department_id, t.position, u.last_name, u.first_name, u.middle_name,
                    d.name_ru AS department_name_ru, d.name_kk AS department_name_kk,
                    p.id AS plan_id, p.rate, p.max_hours, p.status AS plan_status
             FROM teachers t JOIN users u ON u.id = t.user_id
             LEFT JOIN departments d ON d.id = t.department_id
             LEFT JOIN workload_plans p ON p.teacher_id = t.id AND p.academic_year_id = ?
             WHERE ' . implode(' AND ', $where) . ' ORDER BY u.last_name, u.first_name',
            $params
        );
    }

    // ---- assignments ---------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function assignment(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::ASSIGNMENT_COLUMNS . self::ASSIGNMENT_JOINS . ' WHERE wa.id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function createAssignment(array $data): int
    {
        Database::execute(
            'INSERT INTO workload_assignments (plan_id, semester_id, group_id, subject_id, workload_type_id, subgroup, hours, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['plan_id'], $data['semester_id'], $data['group_id'], $data['subject_id'], $data['workload_type_id'], $data['subgroup'], $data['hours'], $data['note'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateAssignment(int $id, array $data): void
    {
        Database::execute('UPDATE workload_assignments SET subgroup = ?, hours = ?, note = ? WHERE id = ?', [$data['subgroup'], $data['hours'], $data['note'], $id]);
    }

    public function deleteAssignment(int $id): void
    {
        Database::execute('DELETE FROM workload_assignments WHERE id = ?', [$id]);
    }

    public function planHours(int $planId, ?int $exceptId = null): int
    {
        return (int) Database::fetchColumn(
            'SELECT COALESCE(SUM(hours), 0) FROM workload_assignments WHERE plan_id = ?' . ($exceptId !== null ? ' AND id <> ?' : ''),
            $exceptId !== null ? [$planId, $exceptId] : [$planId]
        );
    }

    /** Same line already planned for this teacher (same semester, group, subject, kind, subgroup). */
    public function duplicate(int $planId, int $semesterId, ?int $groupId, ?int $subjectId, int $typeId, ?int $subgroup, ?int $exceptId): bool
    {
        return Database::fetchColumn(
            'SELECT 1 FROM workload_assignments WHERE plan_id = ? AND semester_id = ? AND workload_type_id = ?
               AND group_id <=> ? AND subject_id <=> ? AND subgroup <=> ?' . ($exceptId !== null ? ' AND id <> ?' : '') . ' LIMIT 1',
            array_merge([$planId, $semesterId, $typeId, $groupId, $subjectId, $subgroup], $exceptId !== null ? [$exceptId] : [])
        ) !== null;
    }

    /**
     * Hours of one curriculum line already distributed between all teachers, per subgroup
     * (key 0 = whole group).
     *
     * @return array<int, int>
     */
    public function distributed(int $semesterId, int $groupId, int $subjectId, int $typeId, ?int $exceptId = null): array
    {
        $rows = Database::fetchAll(
            'SELECT COALESCE(subgroup, 0) AS sg, SUM(hours) AS hours FROM workload_assignments
             WHERE semester_id = ? AND group_id = ? AND subject_id = ? AND workload_type_id = ?' . ($exceptId !== null ? ' AND id <> ?' : '') . '
             GROUP BY COALESCE(subgroup, 0)',
            array_merge([$semesterId, $groupId, $subjectId, $typeId], $exceptId !== null ? [$exceptId] : [])
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['sg']] = (int) $r['hours'];
        }

        return $out;
    }

    /**
     * Curriculum hours of a column for the group's subject in that semester. The curriculum
     * semester index follows the course the group studies in that academic year.
     */
    public function curriculumHours(int $groupId, int $semesterId, int $subjectId, string $field): ?int
    {
        if (!in_array($field, ['hours_theory', 'hours_practice', 'hours_lab', 'hours_total'], true)) {
            return null;
        }
        $value = Database::fetchColumn(
            "SELECT cs.{$field} FROM `groups` g
             JOIN semesters s ON s.id = ?
             JOIN academic_years y ON y.id = s.academic_year_id
             JOIN curriculum_subjects cs ON cs.curriculum_id = g.curriculum_id AND cs.subject_id = ?
               AND cs.semester = (YEAR(y.start_date) - g.admission_year) * 2 + s.number
             WHERE g.id = ? LIMIT 1",
            [$semesterId, $subjectId, $groupId]
        );

        return $value === null ? null : (int) $value;
    }

    /**
     * Journals (group + subject) of the teacher in the year — the source for auto-fill.
     *
     * @return list<array<string, mixed>>
     */
    public function teacherJournals(int $teacherId, int $yearId): array
    {
        return Database::fetchAll(
            'SELECT a.id, a.semester_id, a.group_id, a.subject_id FROM teacher_subject_assignments a
             JOIN semesters s ON s.id = a.semester_id WHERE a.teacher_id = ? AND s.academic_year_id = ? ORDER BY s.number, a.id',
            [$teacherId, $yearId]
        );
    }

    // ---- actual hours --------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function actuals(int $assignmentId): array
    {
        return Database::fetchAll(
            'SELECT a.*, u.last_name, u.first_name, u.middle_name FROM workload_actuals a LEFT JOIN users u ON u.id = a.created_by
             WHERE a.assignment_id = ? ORDER BY a.work_date DESC, a.id DESC',
            [$assignmentId]
        );
    }

    /** @return array<string, mixed>|null */
    public function actual(int $id): ?array
    {
        return Database::fetch('SELECT * FROM workload_actuals WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function addActual(array $data): int
    {
        Database::execute(
            'INSERT INTO workload_actuals (assignment_id, work_date, hours, note, created_by) VALUES (?, ?, ?, ?, ?)',
            [$data['assignment_id'], $data['work_date'], $data['hours'], $data['note'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    public function deleteActual(int $id): void
    {
        Database::execute('DELETE FROM workload_actuals WHERE id = ?', [$id]);
    }

    public function hasActuals(int $assignmentId): bool
    {
        return Database::fetchColumn('SELECT 1 FROM workload_actuals WHERE assignment_id = ? LIMIT 1', [$assignmentId]) !== null;
    }

    // ---- report sources ------------------------------------------------------

    /**
     * Planned lines with the manual actual hours up to $asOf.
     *
     * @param array<string, mixed> $f year (required), semester, department, teacher, group, subject, type, teachers (scope list|null)
     * @return list<array<string, mixed>>
     */
    public function planLines(array $f, string $asOf): array
    {
        [$where, $params] = $this->filters($f, 'p.teacher_id', 'wa.semester_id', 'wa.group_id', 'wa.subject_id');
        if (!empty($f['type'])) {
            $where[] = 'wa.workload_type_id = ?';
            $params[] = (int) $f['type'];
        }

        return Database::fetchAll(
            'SELECT wa.id AS assignment_id, p.teacher_id, wa.semester_id, wa.group_id, wa.subject_id, wa.workload_type_id, wa.subgroup, wa.hours AS plan,
                    (SELECT COALESCE(SUM(x.hours), 0) FROM workload_actuals x WHERE x.assignment_id = wa.id AND x.work_date <= ?) AS manual_fact
             FROM workload_assignments wa JOIN workload_plans p ON p.id = wa.plan_id JOIN semesters s ON s.id = wa.semester_id
             JOIN teachers t ON t.id = p.teacher_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY wa.id',
            array_merge([$asOf], $params)
        );
    }

    /**
     * Hours of journal lessons held up to $asOf, by the teacher who actually conducted them.
     *
     * @param array<string, mixed> $f
     * @return list<array<string, mixed>>
     */
    public function journalFacts(array $f, string $asOf): array
    {
        [$where, $params] = $this->filters($f, 'COALESCE(jl.conducted_by, a.teacher_id)', 'a.semester_id', 'a.group_id', 'a.subject_id');
        $where[] = 'jl.lesson_date <= ?';
        $params[] = $asOf;

        return Database::fetchAll(
            'SELECT COALESCE(jl.conducted_by, a.teacher_id) AS teacher_id, a.semester_id, a.group_id, a.subject_id, jl.lesson_type, SUM(jl.hours) AS hours
             FROM journal_lessons jl JOIN teacher_subject_assignments a ON a.id = jl.assignment_id JOIN semesters s ON s.id = a.semester_id
             JOIN teachers t ON t.id = COALESCE(jl.conducted_by, a.teacher_id)
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY COALESCE(jl.conducted_by, a.teacher_id), a.semester_id, a.group_id, a.subject_id, jl.lesson_type',
            $params
        );
    }

    /**
     * Labels for the ids used in report lines.
     *
     * @param list<int> $teacherIds
     * @return array{teachers: array<int, array<string, mixed>>, semesters: array<int, array<string, mixed>>, groups: array<int, string>, subjects: array<int, array<string, mixed>>}
     */
    public function labels(array $teacherIds, array $semesterIds, array $groupIds, array $subjectIds): array
    {
        $in = static fn (array $ids): string => implode(',', array_fill(0, max(1, count($ids)), '?'));
        $ids = static fn (array $ids): array => $ids === [] ? [0] : array_values($ids);
        $index = static function (array $rows): array {
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r['id']] = $r;
            }

            return $out;
        };

        return [
            'teachers' => $index(Database::fetchAll(
                'SELECT t.id, t.department_id, u.last_name, u.first_name, u.middle_name, d.name_ru AS department_name_ru, d.name_kk AS department_name_kk
                 FROM teachers t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id
                 WHERE t.id IN (' . $in($teacherIds) . ')',
                $ids($teacherIds)
            )),
            'semesters' => $index(Database::fetchAll('SELECT id, number FROM semesters WHERE id IN (' . $in($semesterIds) . ')', $ids($semesterIds))),
            'groups' => array_map(static fn (array $r): string => (string) $r['code'], $index(Database::fetchAll('SELECT id, code FROM `groups` WHERE id IN (' . $in($groupIds) . ')', $ids($groupIds)))),
            'subjects' => $index(Database::fetchAll('SELECT id, code, name_ru, name_kk FROM subjects WHERE id IN (' . $in($subjectIds) . ')', $ids($subjectIds))),
        ];
    }

    /**
     * Teachers' plan limits for the year.
     *
     * @param list<int> $teacherIds
     * @return array<int, array{max_hours: ?int, rate: ?string}>
     */
    public function limits(int $yearId, array $teacherIds): array
    {
        if ($teacherIds === []) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT teacher_id, max_hours, rate FROM workload_plans WHERE academic_year_id = ? AND teacher_id IN (' . implode(',', array_fill(0, count($teacherIds), '?')) . ')',
            array_merge([$yearId], $teacherIds)
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['teacher_id']] = ['max_hours' => $r['max_hours'] !== null ? (int) $r['max_hours'] : null, 'rate' => $r['rate']];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $f
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function filters(array $f, string $teacher, string $semester, string $group, string $subject): array
    {
        $where = ['s.academic_year_id = ?'];
        $params = [(int) $f['year']];
        if (array_key_exists('teachers', $f) && $f['teachers'] !== null) {
            $where[] = $f['teachers'] === [] ? '1 = 0' : "{$teacher} IN (" . implode(',', array_fill(0, count($f['teachers']), '?')) . ')';
            $params = array_merge($params, $f['teachers']);
        }
        foreach (['semester' => $semester, 'teacher' => $teacher, 'group' => $group, 'subject' => $subject, 'department' => 't.department_id'] as $key => $column) {
            if (!empty($f[$key])) {
                $where[] = "{$column} = ?";
                $params[] = (int) $f[$key];
            }
        }

        return [$where, $params];
    }
}
