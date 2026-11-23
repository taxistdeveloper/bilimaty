<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Timetable data access: regular entries, one-off changes, bell schedule.
 */
final class ScheduleRepository
{
    private const ENTRY_COLUMNS = "e.*, sb.code AS subject_code, sb.name_ru AS subject_name_ru, sb.name_kk AS subject_name_kk,
        g.code AS group_code, g.course, g.status AS group_status,
        tu.last_name AS teacher_last_name, tu.first_name AS teacher_first_name, tu.middle_name AS teacher_middle_name,
        c.number AS classroom_number, c.building AS classroom_building,
        lp.number AS period_number, lp.start_time, lp.end_time";

    private const ENTRY_JOINS = ' FROM schedule_entries e
        JOIN subjects sb ON sb.id = e.subject_id
        JOIN `groups` g ON g.id = e.group_id
        JOIN teachers t ON t.id = e.teacher_id
        JOIN users tu ON tu.id = t.user_id
        LEFT JOIN classrooms c ON c.id = e.classroom_id
        JOIN lesson_periods lp ON lp.id = e.lesson_period_id';

    /** @return list<array<string, mixed>> */
    public function semestersOfYear(int $yearId): array
    {
        return Database::fetchAll('SELECT * FROM semesters WHERE academic_year_id = ? ORDER BY number', [$yearId]);
    }

    /** @return array<string, mixed>|null semester with its year status */
    public function semester(int $id): ?array
    {
        return Database::fetch(
            'SELECT s.*, y.title AS year_title, y.status AS year_status FROM semesters s
             JOIN academic_years y ON y.id = s.academic_year_id WHERE s.id = ?',
            [$id]
        );
    }

    /** @return array<string, mixed>|null semester containing the date */
    public function semesterOn(string $date): ?array
    {
        $id = Database::fetchColumn('SELECT id FROM semesters WHERE start_date <= ? AND end_date >= ? ORDER BY start_date DESC LIMIT 1', [$date, $date]);

        return $id === null ? null : $this->semester((int) $id);
    }

    /** @return list<array<string, mixed>> active bell schedule */
    public function periods(): array
    {
        return Database::fetchAll('SELECT * FROM lesson_periods WHERE is_active = 1 ORDER BY number');
    }

    /** @return array<string, mixed>|null */
    public function period(int $id): ?array
    {
        return Database::fetch('SELECT * FROM lesson_periods WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> all entries of a semester (with display data) */
    public function entries(int $semesterId): array
    {
        return Database::fetchAll(
            'SELECT ' . self::ENTRY_COLUMNS . self::ENTRY_JOINS . ' WHERE e.semester_id = ? ORDER BY e.weekday, lp.number, g.code',
            [$semesterId]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::ENTRY_COLUMNS . self::ENTRY_JOINS . ' WHERE e.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> entries occupying the same weekday + period of the semester */
    public function slotEntries(int $semesterId, int $weekday, int $periodId, ?int $ignoreId): array
    {
        return Database::fetchAll(
            'SELECT ' . self::ENTRY_COLUMNS . self::ENTRY_JOINS . '
             WHERE e.semester_id = ? AND e.weekday = ? AND e.lesson_period_id = ?' . ($ignoreId !== null ? ' AND e.id <> ?' : ''),
            $ignoreId !== null ? [$semesterId, $weekday, $periodId, $ignoreId] : [$semesterId, $weekday, $periodId]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO schedule_entries (semester_id, group_id, subgroup, subject_id, teacher_id, classroom_id, weekday,
                    lesson_period_id, week_parity, lesson_type, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['semester_id'], $data['group_id'], $data['subgroup'], $data['subject_id'], $data['teacher_id'], $data['classroom_id'],
             $data['weekday'], $data['lesson_period_id'], $data['week_parity'], $data['lesson_type'], $data['note'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE schedule_entries SET group_id = ?, subgroup = ?, subject_id = ?, teacher_id = ?, classroom_id = ?, weekday = ?,
                    lesson_period_id = ?, week_parity = ?, lesson_type = ?, note = ? WHERE id = ?',
            [$data['group_id'], $data['subgroup'], $data['subject_id'], $data['teacher_id'], $data['classroom_id'], $data['weekday'],
             $data['lesson_period_id'], $data['week_parity'], $data['lesson_type'], $data['note'], $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM schedule_entries WHERE id = ?', [$id]);
    }

    public function changesCount(int $entryId): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM schedule_changes WHERE schedule_entry_id = ?', [$entryId]);
    }

    /**
     * Changes whose original date OR new date falls in [from, to].
     *
     * @return list<array<string, mixed>>
     */
    public function changesInRange(int $semesterId, string $from, string $to): array
    {
        return Database::fetchAll(
            'SELECT ch.*, nu.last_name AS new_teacher_last_name, nu.first_name AS new_teacher_first_name, nu.middle_name AS new_teacher_middle_name,
                    nc.number AS new_classroom_number, nc.building AS new_classroom_building,
                    np.number AS new_period_number, np.start_time AS new_start_time, np.end_time AS new_end_time
             FROM schedule_changes ch
             JOIN schedule_entries e ON e.id = ch.schedule_entry_id
             LEFT JOIN teachers nt ON nt.id = ch.new_teacher_id LEFT JOIN users nu ON nu.id = nt.user_id
             LEFT JOIN classrooms nc ON nc.id = ch.new_classroom_id
             LEFT JOIN lesson_periods np ON np.id = ch.new_lesson_period_id
             WHERE e.semester_id = ? AND ((ch.lesson_date BETWEEN ? AND ?) OR (ch.new_date BETWEEN ? AND ?))',
            [$semesterId, $from, $to, $from, $to]
        );
    }

    /** @return array<string, mixed>|null */
    public function findChange(int $id): ?array
    {
        return Database::fetch('SELECT * FROM schedule_changes WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function changeFor(int $entryId, string $lessonDate): ?array
    {
        return Database::fetch('SELECT * FROM schedule_changes WHERE schedule_entry_id = ? AND lesson_date = ?', [$entryId, $lessonDate]);
    }

    /** Insert or replace the change of one occurrence. @param array<string, mixed> $data */
    public function saveChange(array $data): int
    {
        Database::execute(
            'INSERT INTO schedule_changes (schedule_entry_id, lesson_date, change_type, new_teacher_id, new_classroom_id, new_date,
                    new_lesson_period_id, reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE change_type = VALUES(change_type), new_teacher_id = VALUES(new_teacher_id),
                    new_classroom_id = VALUES(new_classroom_id), new_date = VALUES(new_date),
                    new_lesson_period_id = VALUES(new_lesson_period_id), reason = VALUES(reason), created_by = VALUES(created_by)',
            [$data['schedule_entry_id'], $data['lesson_date'], $data['change_type'], $data['new_teacher_id'], $data['new_classroom_id'],
             $data['new_date'], $data['new_lesson_period_id'], $data['reason'], $data['created_by']]
        );

        return (int) Database::fetchColumn('SELECT id FROM schedule_changes WHERE schedule_entry_id = ? AND lesson_date = ?', [$data['schedule_entry_id'], $data['lesson_date']]);
    }

    public function deleteChange(int $id): void
    {
        Database::execute('DELETE FROM schedule_changes WHERE id = ?', [$id]);
    }

    /**
     * Changes journal with filters.
     *
     * @param array{from?: string, to?: string, group?: ?int, teacher?: ?int} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function changesJournal(array $filters, int $page, int $perPage = 30): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'GREATEST(ch.lesson_date, COALESCE(ch.new_date, ch.lesson_date)) >= ?';
            $params[] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'LEAST(ch.lesson_date, COALESCE(ch.new_date, ch.lesson_date)) <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['group'])) {
            $where[] = 'e.group_id = ?';
            $params[] = (int) $filters['group'];
        }
        if (!empty($filters['teacher'])) {
            $where[] = '(e.teacher_id = ? OR ch.new_teacher_id = ?)';
            array_push($params, (int) $filters['teacher'], (int) $filters['teacher']);
        }
        $from = ' FROM schedule_changes ch JOIN schedule_entries e ON e.id = ch.schedule_entry_id
            JOIN subjects sb ON sb.id = e.subject_id JOIN `groups` g ON g.id = e.group_id
            JOIN teachers t ON t.id = e.teacher_id JOIN users tu ON tu.id = t.user_id
            JOIN lesson_periods lp ON lp.id = e.lesson_period_id
            LEFT JOIN teachers nt ON nt.id = ch.new_teacher_id LEFT JOIN users nu ON nu.id = nt.user_id
            LEFT JOIN classrooms oc ON oc.id = e.classroom_id LEFT JOIN classrooms nc ON nc.id = ch.new_classroom_id
            LEFT JOIN lesson_periods np ON np.id = ch.new_lesson_period_id
            LEFT JOIN users au ON au.id = ch.created_by
            WHERE ' . implode(' AND ', $where);

        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . $from, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $items = Database::fetchAll(
            'SELECT ch.*, e.group_id, e.subgroup, g.code AS group_code, sb.code AS subject_code, sb.name_ru AS subject_name_ru, sb.name_kk AS subject_name_kk,
                    lp.number AS period_number, np.number AS new_period_number,
                    tu.last_name AS teacher_last_name, tu.first_name AS teacher_first_name, tu.middle_name AS teacher_middle_name,
                    nu.last_name AS new_teacher_last_name, nu.first_name AS new_teacher_first_name, nu.middle_name AS new_teacher_middle_name,
                    oc.number AS classroom_number, oc.building AS classroom_building, nc.number AS new_classroom_number, nc.building AS new_classroom_building,
                    au.last_name AS author_last_name, au.first_name AS author_first_name, au.middle_name AS author_middle_name'
            . $from . ' ORDER BY ch.lesson_date DESC, ch.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /**
     * Calendar events that suspend regular lessons in [from, to].
     *
     * @param list<string> $types
     * @return list<array<string, mixed>>
     */
    public function suspendingEvents(int $yearId, string $from, string $to, array $types): array
    {
        if ($types === []) {
            return [];
        }

        return Database::fetchAll(
            'SELECT id, type, title_ru, title_kk, start_date, end_date, course, group_id FROM academic_calendar_events
             WHERE academic_year_id = ? AND start_date <= ? AND end_date >= ? AND type IN (' . implode(',', array_fill(0, count($types), '?')) . ')',
            [$yearId, $to, $from, ...$types]
        );
    }

    /** @return array<string, mixed>|null */
    public function teacher(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.id, t.status, u.last_name, u.first_name, u.middle_name FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = ?',
            [$id]
        );
    }

    /** @return array<string, mixed>|null */
    public function classroom(int $id): ?array
    {
        return Database::fetch('SELECT * FROM classrooms WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null group with course and curriculum */
    public function group(int $id): ?array
    {
        return Database::fetch('SELECT id, code, course, status, curriculum_id FROM `groups` WHERE id = ?', [$id]);
    }

    public function subjectInCurriculum(int $curriculumId, int $subjectId, int $planSemester): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM curriculum_subjects WHERE curriculum_id = ? AND subject_id = ? AND semester = ?',
            [$curriculumId, $subjectId, $planSemester]
        ) > 0;
    }

    public function teacherIdOfUser(int $userId): ?int
    {
        $id = Database::fetchColumn('SELECT id FROM teachers WHERE user_id = ?', [$userId]);

        return $id === null ? null : (int) $id;
    }

    public function groupOfStudentUser(int $userId): ?int
    {
        $id = Database::fetchColumn("SELECT current_group_id FROM students WHERE user_id = ? AND status = 'active'", [$userId]);

        return $id === null ? null : (int) $id;
    }
}
