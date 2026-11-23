<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Electronic journals (teacher_subject_assignments) and their content.
 */
final class JournalRepository
{
    private const COLUMNS = "a.*, s.number AS semester_number, s.start_date AS semester_start, s.end_date AS semester_end,
        s.academic_year_id, y.title AS year_title, y.status AS year_status,
        g.code AS group_code, g.course, sp.department_id,
        sb.code AS subject_code, sb.name_ru AS subject_name_ru, sb.name_kk AS subject_name_kk,
        tu.id AS teacher_user_id, tu.last_name AS teacher_last_name, tu.first_name AS teacher_first_name, tu.middle_name AS teacher_middle_name,
        gs.title AS scheme_title, gs.version AS scheme_version,
        (SELECT COUNT(*) FROM journal_lessons jl WHERE jl.assignment_id = a.id) AS lessons_count";

    private const JOINS = ' FROM teacher_subject_assignments a
        JOIN semesters s ON s.id = a.semester_id
        JOIN academic_years y ON y.id = s.academic_year_id
        JOIN `groups` g ON g.id = a.group_id
        JOIN specialties sp ON sp.id = g.specialty_id
        JOIN subjects sb ON sb.id = a.subject_id
        JOIN teachers t ON t.id = a.teacher_id
        JOIN users tu ON tu.id = t.user_id
        LEFT JOIN grading_schemes gs ON gs.id = a.grading_scheme_id';

    /**
     * Journals visible to the user: own (responsible or co-teacher by timetable) and
     * those of groups in the user's AccessScope.
     *
     * @param array{semester?: ?int, group?: ?int, subject?: ?int, teacher?: ?int, status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters, ?int $myTeacherId, bool $onlyMine = false): array
    {
        $params = [];
        $visibility = [];
        if ($myTeacherId !== null) {
            $visibility[] = 'a.teacher_id = ?';
            $visibility[] = 'EXISTS (SELECT 1 FROM schedule_entries se WHERE se.semester_id = a.semester_id AND se.group_id = a.group_id
                                   AND se.subject_id = a.subject_id AND se.teacher_id = ?)';
            array_push($params, $myTeacherId, $myTeacherId);
        }
        if (!$onlyMine) {
            [$scopeSql, $scopeParams] = AccessScope::condition('a.group_id', AccessScope::groupIds());
            $visibility[] = $scopeSql;
            array_push($params, ...$scopeParams);
        }
        $where = [$visibility === [] ? '1 = 0' : '(' . implode(' OR ', $visibility) . ')'];

        foreach (['semester' => 'a.semester_id', 'group' => 'a.group_id', 'subject' => 'a.subject_id', 'teacher' => 'a.teacher_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[] = "{$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (in_array($filters['status'] ?? '', ['open', 'submitted', 'approved'], true)) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY s.start_date DESC, g.code, sb.code LIMIT 500',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE a.id = ?', [$id]);
    }

    public function isCoTeacher(array $journal, int $teacherId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM schedule_entries WHERE semester_id = ? AND group_id = ? AND subject_id = ? AND teacher_id = ?',
            [$journal['semester_id'], $journal['group_id'], $journal['subject_id'], $teacherId]
        ) > 0;
    }

    /**
     * Students who belonged to the group at any time during the semester (from the group
     * history), with the period of membership — transferred students keep their marks.
     *
     * @return list<array<string, mixed>>
     */
    public function students(array $journal): array
    {
        return Database::fetchAll(
            "SELECT st.id, st.student_number, st.status, u.last_name, u.first_name, u.middle_name,
                    MIN(h.start_date) AS member_from,
                    IF(SUM(h.end_date IS NULL) > 0, NULL, MAX(h.end_date)) AS member_to
             FROM student_group_history h
             JOIN students st ON st.id = h.student_id
             JOIN users u ON u.id = st.user_id
             WHERE h.group_id = ? AND h.start_date <= ? AND (h.end_date IS NULL OR h.end_date >= ?)
             GROUP BY st.id, st.student_number, st.status, u.last_name, u.first_name, u.middle_name
             ORDER BY u.last_name, u.first_name",
            [$journal['group_id'], $journal['semester_end'], $journal['semester_start']]
        );
    }

    public function studentBelongs(array $journal, int $studentId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM student_group_history WHERE group_id = ? AND student_id = ? AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)',
            [$journal['group_id'], $studentId, $journal['semester_end'], $journal['semester_start']]
        ) > 0;
    }

    public function studentMemberOn(array $journal, int $studentId, string $date): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM student_group_history WHERE group_id = ? AND student_id = ? AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)',
            [$journal['group_id'], $studentId, $date, $date]
        ) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function lessons(int $journalId): array
    {
        return Database::fetchAll(
            'SELECT jl.*, lp.number AS period_number, cu.last_name AS conducted_last_name, cu.first_name AS conducted_first_name, cu.middle_name AS conducted_middle_name
             FROM journal_lessons jl JOIN lesson_periods lp ON lp.id = jl.lesson_period_id
             LEFT JOIN teachers ct ON ct.id = jl.conducted_by LEFT JOIN users cu ON cu.id = ct.user_id
             WHERE jl.assignment_id = ? ORDER BY jl.lesson_date, lp.number',
            [$journalId]
        );
    }

    /** @return array<string, mixed>|null */
    public function lesson(int $journalId, int $lessonId): ?array
    {
        return Database::fetch('SELECT * FROM journal_lessons WHERE id = ? AND assignment_id = ?', [$lessonId, $journalId]);
    }

    /** @return array<int, array<int, array<string, mixed>>> lesson_id => student_id => grade row */
    public function grades(int $journalId): array
    {
        $map = [];
        foreach (Database::fetchAll(
            'SELECT g.* FROM journal_grades g JOIN journal_lessons jl ON jl.id = g.journal_lesson_id WHERE jl.assignment_id = ?',
            [$journalId]
        ) as $row) {
            $map[(int) $row['journal_lesson_id']][(int) $row['student_id']] = $row;
        }

        return $map;
    }

    /** @return array<int, array<int, string>> lesson_id => student_id => status */
    public function attendance(int $journalId): array
    {
        $map = [];
        foreach (Database::fetchAll(
            'SELECT at.journal_lesson_id, at.student_id, at.status FROM attendance at JOIN journal_lessons jl ON jl.id = at.journal_lesson_id WHERE jl.assignment_id = ?',
            [$journalId]
        ) as $row) {
            $map[(int) $row['journal_lesson_id']][(int) $row['student_id']] = (string) $row['status'];
        }

        return $map;
    }

    /** @return array<int, array<int, array<string, mixed>>> student_id => assessment_type_id => row */
    public function assessments(int $journalId): array
    {
        $map = [];
        foreach (Database::fetchAll('SELECT * FROM student_assessments WHERE assignment_id = ?', [$journalId]) as $row) {
            $map[(int) $row['student_id']][(int) $row['assessment_type_id']] = $row;
        }

        return $map;
    }

    /** @return array<string, mixed>|null */
    public function assessmentType(string $code): ?array
    {
        return Database::fetch('SELECT * FROM assessment_types WHERE code = ?', [$code]);
    }

    // ------------------------------------------------------------------ writes

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO teacher_subject_assignments (semester_id, group_id, subject_id, teacher_id, grading_scheme_id) VALUES (?, ?, ?, ?, ?)',
            [$data['semester_id'], $data['group_id'], $data['subject_id'], $data['teacher_id'], $data['grading_scheme_id']]
        );

        return Database::lastInsertId();
    }

    public function exists(int $semesterId, int $groupId, int $subjectId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM teacher_subject_assignments WHERE semester_id = ? AND group_id = ? AND subject_id = ?',
            [$semesterId, $groupId, $subjectId]
        ) > 0;
    }

    public function updateSettings(int $id, int $teacherId, ?int $schemeId): void
    {
        Database::execute('UPDATE teacher_subject_assignments SET teacher_id = ?, grading_scheme_id = ? WHERE id = ?', [$teacherId, $schemeId, $id]);
    }

    public function setStatus(int $id, string $status, ?int $userId): void
    {
        Database::execute(
            "UPDATE teacher_subject_assignments SET status = ?,
                    submitted_at = IF(? = 'submitted', NOW(), submitted_at),
                    approved_at = IF(? = 'approved', NOW(), IF(? = 'open', NULL, approved_at)),
                    approved_by = IF(? = 'approved', ?, IF(? = 'open', NULL, approved_by))
             WHERE id = ?",
            [$status, $status, $status, $status, $status, $userId, $status, $id]
        );
    }

    /**
     * Distinct group + subject pairs of the semester timetable with the teacher who has most lessons.
     *
     * @return list<array<string, mixed>>
     */
    public function timetablePairs(int $semesterId): array
    {
        return Database::fetchAll(
            'SELECT group_id, subject_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(teacher_id ORDER BY cnt DESC, teacher_id), \',\', 1) AS teacher_id
             FROM (SELECT group_id, subject_id, teacher_id, COUNT(*) AS cnt FROM schedule_entries WHERE semester_id = ?
                   GROUP BY group_id, subject_id, teacher_id) x
             GROUP BY group_id, subject_id',
            [$semesterId]
        );
    }

    /** @param array<string, mixed> $data */
    public function saveLesson(int $journalId, ?int $lessonId, array $data): int
    {
        if ($lessonId === null) {
            Database::execute(
                'INSERT INTO journal_lessons (assignment_id, lesson_date, lesson_period_id, lesson_type, topic, homework, note, hours, conducted_by, schedule_entry_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$journalId, $data['lesson_date'], $data['lesson_period_id'], $data['lesson_type'], $data['topic'], $data['homework'],
                 $data['note'], $data['hours'], $data['conducted_by'], $data['schedule_entry_id'] ?? null, $data['created_by'] ?? null]
            );

            return Database::lastInsertId();
        }
        Database::execute(
            'UPDATE journal_lessons SET lesson_date = ?, lesson_period_id = ?, lesson_type = ?, topic = ?, homework = ?, note = ?, hours = ?, conducted_by = ?
             WHERE id = ? AND assignment_id = ?',
            [$data['lesson_date'], $data['lesson_period_id'], $data['lesson_type'], $data['topic'], $data['homework'], $data['note'],
             $data['hours'], $data['conducted_by'], $lessonId, $journalId]
        );

        return $lessonId;
    }

    public function lessonSlotTaken(int $journalId, string $date, int $periodId, ?int $ignoreId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM journal_lessons WHERE assignment_id = ? AND lesson_date = ? AND lesson_period_id = ?' . ($ignoreId !== null ? ' AND id <> ?' : ''),
            $ignoreId !== null ? [$journalId, $date, $periodId, $ignoreId] : [$journalId, $date, $periodId]
        ) > 0;
    }

    public function deleteLesson(int $journalId, int $lessonId): void
    {
        Database::execute('DELETE FROM journal_lessons WHERE id = ? AND assignment_id = ?', [$lessonId, $journalId]);
    }

    public function lessonHasMarks(int $lessonId): int
    {
        return (int) Database::fetchColumn(
            'SELECT (SELECT COUNT(*) FROM journal_grades WHERE journal_lesson_id = ? AND (value IS NOT NULL OR comment IS NOT NULL))
                  + (SELECT COUNT(*) FROM attendance WHERE journal_lesson_id = ?)',
            [$lessonId, $lessonId]
        );
    }

    /** @return array<string, mixed>|null */
    public function gradeCell(int $lessonId, int $studentId): ?array
    {
        return Database::fetch('SELECT * FROM journal_grades WHERE journal_lesson_id = ? AND student_id = ?', [$lessonId, $studentId]);
    }

    public function attendanceCell(int $lessonId, int $studentId): ?string
    {
        $status = Database::fetchColumn('SELECT status FROM attendance WHERE journal_lesson_id = ? AND student_id = ?', [$lessonId, $studentId]);

        return $status === null ? null : (string) $status;
    }

    public function saveGrade(int $lessonId, int $studentId, ?string $value, ?string $comment, ?int $userId): void
    {
        if ($value === null && ($comment === null || $comment === '')) {
            Database::execute('DELETE FROM journal_grades WHERE journal_lesson_id = ? AND student_id = ?', [$lessonId, $studentId]);

            return;
        }
        Database::execute(
            'INSERT INTO journal_grades (journal_lesson_id, student_id, value, comment, updated_by) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), comment = VALUES(comment), updated_by = VALUES(updated_by)',
            [$lessonId, $studentId, $value, $comment !== '' ? $comment : null, $userId]
        );
    }

    public function saveAttendance(int $lessonId, int $studentId, ?string $status, ?int $userId): void
    {
        if ($status === null) {
            Database::execute('DELETE FROM attendance WHERE journal_lesson_id = ? AND student_id = ?', [$lessonId, $studentId]);

            return;
        }
        Database::execute(
            'INSERT INTO attendance (journal_lesson_id, student_id, status, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by)',
            [$lessonId, $studentId, $status, $userId]
        );
    }

    public function saveAssessment(int $journalId, int $studentId, int $typeId, ?string $value, ?int $schemeId, ?int $userId): void
    {
        if ($value === null) {
            Database::execute('DELETE FROM student_assessments WHERE assignment_id = ? AND student_id = ? AND assessment_type_id = ?', [$journalId, $studentId, $typeId]);

            return;
        }
        Database::execute(
            'INSERT INTO student_assessments (assignment_id, student_id, assessment_type_id, value, grading_scheme_id, updated_by) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), grading_scheme_id = VALUES(grading_scheme_id), updated_by = VALUES(updated_by)',
            [$journalId, $studentId, $typeId, $value, $schemeId, $userId]
        );
    }

    public function deleteFinals(int $journalId, int $finalTypeId): void
    {
        Database::execute('DELETE FROM student_assessments WHERE assignment_id = ? AND assessment_type_id = ?', [$journalId, $finalTypeId]);
    }

    /** @param array<string, mixed> $row */
    public function logChange(array $row): void
    {
        Database::execute(
            'INSERT INTO grade_change_history (assignment_id, student_id, journal_lesson_id, assessment_type_id, field, old_value, new_value, changed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$row['assignment_id'], $row['student_id'] ?? null, $row['journal_lesson_id'] ?? null, $row['assessment_type_id'] ?? null,
             $row['field'], $row['old_value'] ?? null, $row['new_value'] ?? null, $row['changed_by'] ?? null]
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function history(int $journalId, ?int $studentId, int $page, int $perPage = 40): array
    {
        $where = 'h.assignment_id = ?' . ($studentId !== null ? ' AND h.student_id = ?' : '');
        $params = $studentId !== null ? [$journalId, $studentId] : [$journalId];
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM grade_change_history h WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $items = Database::fetchAll(
            "SELECT h.*, su.last_name, su.first_name, su.middle_name, jl.lesson_date, lp.number AS period_number,
                    t.short_ru, t.short_kk, cu.last_name AS author_last_name, cu.first_name AS author_first_name, cu.middle_name AS author_middle_name
             FROM grade_change_history h
             LEFT JOIN students st ON st.id = h.student_id LEFT JOIN users su ON su.id = st.user_id
             LEFT JOIN journal_lessons jl ON jl.id = h.journal_lesson_id LEFT JOIN lesson_periods lp ON lp.id = jl.lesson_period_id
             LEFT JOIN assessment_types t ON t.id = h.assessment_type_id
             LEFT JOIN users cu ON cu.id = h.changed_by
             WHERE {$where} ORDER BY h.id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return list<array<string, mixed>> journals of the student's groups (for "My grades") */
    public function journalsOfStudent(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT DISTINCT ' . self::COLUMNS . self::JOINS . '
             JOIN student_group_history h ON h.group_id = a.group_id AND h.student_id = ?
                  AND h.start_date <= s.end_date AND (h.end_date IS NULL OR h.end_date >= s.start_date)
             ORDER BY s.start_date DESC, sb.code',
            [$studentId]
        );
    }

    public function studentIdOfUser(int $userId): ?int
    {
        $id = Database::fetchColumn('SELECT id FROM students WHERE user_id = ?', [$userId]);

        return $id === null ? null : (int) $id;
    }
}
