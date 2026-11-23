<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Exam/rating events, statements (exam_results) and retakes.
 */
final class ExamRepository
{
    private const COLUMNS = "e.*, a.group_id, a.subject_id, a.semester_id, a.teacher_id AS journal_teacher_id, a.grading_scheme_id, a.status AS journal_status,
        g.code AS group_code, g.course, sb.code AS subject_code, sb.name_ru AS subject_name_ru, sb.name_kk AS subject_name_kk,
        t.code AS type_code, t.name_ru AS type_name_ru, t.name_kk AS type_name_kk, t.short_ru AS type_short_ru, t.short_kk AS type_short_kk,
        eu.last_name AS examiner_last_name, eu.first_name AS examiner_first_name, eu.middle_name AS examiner_middle_name,
        c.number AS classroom_number, c.building AS classroom_building,
        s.number AS semester_number, s.start_date AS semester_start, s.end_date AS semester_end, y.title AS year_title, y.start_date AS year_start, y.end_date AS year_end, y.status AS year_status,
        (SELECT COUNT(*) FROM exam_results r WHERE r.exam_id = e.id AND (r.value IS NOT NULL OR r.attendance IS NOT NULL OR r.admission = 'not_admitted')) AS graded_count";

    private const JOINS = ' FROM exams e
        JOIN teacher_subject_assignments a ON a.id = e.assignment_id
        JOIN `groups` g ON g.id = a.group_id
        JOIN subjects sb ON sb.id = a.subject_id
        JOIN assessment_types t ON t.id = e.assessment_type_id
        JOIN teachers et ON et.id = e.examiner_teacher_id JOIN users eu ON eu.id = et.user_id
        LEFT JOIN classrooms c ON c.id = e.classroom_id
        JOIN semesters s ON s.id = a.semester_id JOIN academic_years y ON y.id = s.academic_year_id';

    /**
     * @param array{semester?: ?int, group?: ?int, type?: ?int, status?: string, from?: string, to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters, ?int $myTeacherId): array
    {
        [$scopeSql, $params] = AccessScope::condition('a.group_id', AccessScope::groupIds());
        $visibility = [$scopeSql];
        if ($myTeacherId !== null) {
            $visibility[] = 'e.examiner_teacher_id = ?';
            $visibility[] = 'a.teacher_id = ?';
            array_push($params, $myTeacherId, $myTeacherId);
        }
        $where = ['(' . implode(' OR ', $visibility) . ')'];
        foreach (['semester' => 'a.semester_id', 'group' => 'a.group_id', 'type' => 'e.assessment_type_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (in_array($filters['status'] ?? '', ['planned', 'open', 'closed'], true)) {
            $where[] = 'e.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'e.exam_date >= ?';
            $params[] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'e.exam_date <= ?';
            $params[] = $filters['to'];
        }

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY e.exam_date, e.start_time, g.code LIMIT 500',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE e.id = ?', [$id]);
    }

    public function existsFor(int $assignmentId, int $typeId, ?int $ignoreId = null): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM exams WHERE assignment_id = ? AND assessment_type_id = ?' . ($ignoreId !== null ? ' AND id <> ?' : ''),
            $ignoreId !== null ? [$assignmentId, $typeId, $ignoreId] : [$assignmentId, $typeId]
        ) > 0;
    }

    /**
     * Same-day clashes: the group already has an event that day; the examiner or the classroom
     * are busy at the same start time.
     *
     * @return list<array<string, mixed>>
     */
    public function clashes(int $groupId, string $date, ?string $time, int $examinerId, ?int $classroomId, ?int $ignoreId): array
    {
        $sql = 'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE e.exam_date = ? AND (a.group_id = ?';
        $params = [$date, $groupId];
        if ($time !== null) {
            $sql .= ' OR (e.start_time = ? AND (e.examiner_teacher_id = ?' . ($classroomId !== null ? ' OR e.classroom_id = ?' : '') . '))';
            array_push($params, $time, $examinerId);
            if ($classroomId !== null) {
                $params[] = $classroomId;
            }
        }
        $sql .= ')';
        if ($ignoreId !== null) {
            $sql .= ' AND e.id <> ?';
            $params[] = $ignoreId;
        }

        return Database::fetchAll($sql, $params);
    }

    public function nextStatementNumber(int $year): string
    {
        $prefix = 'В-' . $year . '-';
        $last = Database::fetchColumn('SELECT MAX(statement_number) FROM exams WHERE statement_number LIKE ?', [$prefix . '%']);
        $next = $last !== null ? (int) substr((string) $last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO exams (assignment_id, assessment_type_id, exam_form, period_start, exam_date, start_time, classroom_id, examiner_teacher_id, statement_number, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['assignment_id'], $data['assessment_type_id'], $data['exam_form'], $data['period_start'], $data['exam_date'], $data['start_time'],
             $data['classroom_id'], $data['examiner_teacher_id'], $data['statement_number'], $data['note'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE exams SET exam_form = ?, period_start = ?, exam_date = ?, start_time = ?, classroom_id = ?, examiner_teacher_id = ?, note = ? WHERE id = ?',
            [$data['exam_form'], $data['period_start'], $data['exam_date'], $data['start_time'], $data['classroom_id'], $data['examiner_teacher_id'], $data['note'], $id]
        );
    }

    public function setStatus(int $id, string $status, ?int $userId): void
    {
        Database::execute(
            "UPDATE exams SET status = ?, closed_at = IF(? = 'closed', NOW(), NULL), closed_by = IF(? = 'closed', ?, NULL) WHERE id = ?",
            [$status, $status, $status, $userId, $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute("DELETE FROM exams WHERE id = ? AND status = 'planned'", [$id]);
    }

    /** @return array<int, array<string, mixed>> student_id => result row */
    public function results(int $examId): array
    {
        $rows = Database::fetchAll('SELECT * FROM exam_results WHERE exam_id = ?', [$examId]);

        return array_column($rows, null, 'student_id');
    }

    /** @return array<string, mixed>|null */
    public function result(int $examId, int $studentId): ?array
    {
        return Database::fetch('SELECT * FROM exam_results WHERE exam_id = ? AND student_id = ?', [$examId, $studentId]);
    }

    public function saveResult(int $examId, int $studentId, string $admission, ?string $attendance, ?string $value, ?string $note, ?int $userId): void
    {
        Database::execute(
            'INSERT INTO exam_results (exam_id, student_id, admission, attendance, value, note, graded_by, graded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE admission = VALUES(admission), attendance = VALUES(attendance), value = VALUES(value),
                    note = VALUES(note), graded_by = VALUES(graded_by), graded_at = NOW()',
            [$examId, $studentId, $admission, $attendance, $value, $note, $userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function retakes(int $examId): array
    {
        return Database::fetchAll(
            'SELECT r.*, u.last_name, u.first_name, u.middle_name, tu.last_name AS examiner_last_name, tu.first_name AS examiner_first_name, tu.middle_name AS examiner_middle_name
             FROM retakes r JOIN students st ON st.id = r.student_id JOIN users u ON u.id = st.user_id
             JOIN teachers t ON t.id = r.examiner_teacher_id JOIN users tu ON tu.id = t.user_id
             WHERE r.exam_id = ? ORDER BY r.student_id, r.attempt_no',
            [$examId]
        );
    }

    /** @return array<string, mixed>|null */
    public function retake(int $id): ?array
    {
        return Database::fetch('SELECT * FROM retakes WHERE id = ?', [$id]);
    }

    public function nextAttempt(int $examId, int $studentId): int
    {
        return (int) Database::fetchColumn('SELECT COALESCE(MAX(attempt_no), 0) + 1 FROM retakes WHERE exam_id = ? AND student_id = ?', [$examId, $studentId]);
    }

    public function hasScheduledRetake(int $examId, int $studentId): bool
    {
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM retakes WHERE exam_id = ? AND student_id = ? AND status = 'scheduled'", [$examId, $studentId]) > 0;
    }

    /** @param array<string, mixed> $data */
    public function createRetake(array $data): int
    {
        Database::execute(
            'INSERT INTO retakes (exam_id, student_id, attempt_no, retake_date, examiner_teacher_id, old_value, reason, order_number, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['exam_id'], $data['student_id'], $data['attempt_no'], $data['retake_date'], $data['examiner_teacher_id'],
             $data['old_value'], $data['reason'], $data['order_number'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    public function finishRetake(int $id, string $status, ?string $newValue): void
    {
        Database::execute(
            "UPDATE retakes SET status = ?, new_value = ?, completed_at = IF(? = 'completed', NOW(), NULL) WHERE id = ?",
            [$status, $newValue, $status, $id]
        );
    }

    /** @return list<array<string, mixed>> exams of the student's groups with own result */
    public function forStudent(int $studentId, ?string $from = null): array
    {
        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . ', r.admission AS my_admission, r.attendance AS my_attendance, r.value AS my_value' . self::JOINS . '
             LEFT JOIN exam_results r ON r.exam_id = e.id AND r.student_id = ?
             WHERE EXISTS (SELECT 1 FROM student_group_history h WHERE h.group_id = a.group_id AND h.student_id = ?
                           AND h.start_date <= s.end_date AND (h.end_date IS NULL OR h.end_date >= s.start_date))'
            . ($from !== null ? ' AND e.exam_date >= ?' : '') . ' ORDER BY e.exam_date, e.start_time',
            $from !== null ? [$studentId, $studentId, $from] : [$studentId, $studentId]
        );
    }

    /** @return list<array<string, mixed>> upcoming events where the teacher is the examiner */
    public function upcomingForTeacher(int $teacherId, string $from, int $limit = 5): array
    {
        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE e.examiner_teacher_id = ? AND e.exam_date >= ? ORDER BY e.exam_date, e.start_time LIMIT ' . max(1, $limit),
            [$teacherId, $from]
        );
    }
}
