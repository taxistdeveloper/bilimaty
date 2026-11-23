<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Practice assignments and their schedules. */
final class PracticeRepository
{
    private const COLUMNS = 'p.*, g.code AS group_code, g.course, sp.department_id, t.user_id AS teacher_user_id,
        tu.last_name AS teacher_last_name, tu.first_name AS teacher_first_name, tu.middle_name AS teacher_middle_name,
        (SELECT COUNT(*) FROM practice_entries e WHERE e.assignment_id = p.id) AS entries_count,
        (SELECT MIN(e.entry_date) FROM practice_entries e WHERE e.assignment_id = p.id) AS first_date,
        (SELECT MAX(e.entry_date) FROM practice_entries e WHERE e.assignment_id = p.id) AS last_date';

    private const JOINS = ' FROM practice_assignments p
        JOIN `groups` g ON g.id = p.group_id
        JOIN specialties sp ON sp.id = g.specialty_id
        JOIN teachers t ON t.id = p.teacher_id
        JOIN users tu ON tu.id = t.user_id';

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE p.id = ?', [$id]);
    }

    /**
     * @param array{status?: string, teacher?: int, group?: int, overdue?: bool} $filters
     * @param list<int>|null $groupIds null = whole college
     * @return list<array<string, mixed>>
     */
    public function list(array $filters, ?array $groupIds, ?int $teacherId = null): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($groupIds !== null) {
            if ($groupIds === []) {
                return [];
            }
            $where[] = 'p.group_id IN (' . implode(',', array_fill(0, count($groupIds), '?')) . ')';
            array_push($params, ...$groupIds);
        }
        if ($teacherId !== null) {
            $where[] = 'p.teacher_id = ?';
            $params[] = $teacherId;
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['teacher'] ?? 0) > 0) {
            $where[] = 'p.teacher_id = ?';
            $params[] = $filters['teacher'];
        }
        if (($filters['group'] ?? 0) > 0) {
            $where[] = 'p.group_id = ?';
            $params[] = $filters['group'];
        }
        if (!empty($filters['overdue'])) {
            $where[] = "p.deadline < CURDATE() AND p.status IN ('assigned','in_progress','returned')";
        }

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY FIELD(p.status, 'submitted', 'returned', 'in_progress', 'assigned', 'approved'), p.deadline, g.code LIMIT 500",
            $params
        );
    }

    /** @return list<array<string, mixed>> rows of the schedule in order */
    public function entries(int $assignmentId): array
    {
        return Database::fetchAll(
            'SELECT * FROM practice_entries WHERE assignment_id = ? ORDER BY sort_order, id',
            [$assignmentId]
        );
    }

    /**
     * Schedule rows on a date (all practices), for the "practice on a date" report.
     *
     * @param list<int>|null $groupIds
     * @return list<array<string, mixed>>
     */
    public function onDate(string $date, ?array $groupIds, ?int $teacherId = null): array
    {
        $where = ['e.entry_date = ?'];
        $params = [$date];
        if ($groupIds !== null) {
            if ($groupIds === []) {
                return [];
            }
            $where[] = 'p.group_id IN (' . implode(',', array_fill(0, count($groupIds), '?')) . ')';
            array_push($params, ...$groupIds);
        }
        if ($teacherId !== null) {
            $where[] = 'p.teacher_id = ?';
            $params[] = $teacherId;
        }

        return Database::fetchAll(
            'SELECT e.*, p.id AS assignment_id, p.title, p.practice_type, p.status, g.code AS group_code,
                    tu.last_name AS teacher_last_name, tu.first_name AS teacher_first_name, tu.middle_name AS teacher_middle_name
             FROM practice_entries e JOIN practice_assignments p ON p.id = e.assignment_id
             JOIN `groups` g ON g.id = p.group_id JOIN teachers t ON t.id = p.teacher_id JOIN users tu ON tu.id = t.user_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY e.time_start IS NULL, e.time_start, tu.last_name',
            $params
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO practice_assignments (teacher_id, group_id, title, practice_type, module_name, hours, deadline, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['teacher_id'], $data['group_id'], $data['title'], $data['practice_type'], $data['module_name'], $data['hours'], $data['deadline'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = ['teacher_id', 'group_id', 'title', 'practice_type', 'module_name', 'hours', 'deadline', 'status', 'teacher_comment',
            'return_reason', 'submitted_at', 'decided_at', 'decided_by'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data)));
        Database::execute("UPDATE practice_assignments SET {$set} WHERE id = ?", [...array_values($data), $id]);
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM practice_assignments WHERE id = ?', [$id]);
    }

    /** Replace the schedule rows. @param list<array<string, mixed>> $entries */
    public function replaceEntries(int $assignmentId, array $entries): void
    {
        Database::execute('DELETE FROM practice_entries WHERE assignment_id = ?', [$assignmentId]);
        foreach (array_values($entries) as $i => $e) {
            Database::execute(
                'INSERT INTO practice_entries (assignment_id, sort_order, topic, entry_date, time_start, time_end, place, is_distance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$assignmentId, $i, $e['topic'], $e['entry_date'], $e['time_start'], $e['time_end'], $e['place'], $e['is_distance'] ? 1 : 0]
            );
        }
    }

    /** Public holidays of the academic calendar between two dates. @return list<string> Y-m-d */
    public function holidays(string $from, string $to): array
    {
        $out = [];
        foreach (Database::fetchAll(
            "SELECT start_date, end_date FROM academic_calendar_events WHERE type = 'public_holiday' AND start_date <= ? AND end_date >= ?",
            [$to, $from]
        ) as $event) {
            for ($d = max($from, (string) $event['start_date']); $d <= min($to, (string) $event['end_date']); $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
                $out[$d] = true;
            }
        }

        return array_keys($out);
    }

    /** @return array<string, int> status => count, plus 'overdue' */
    public function counts(?array $groupIds, ?int $teacherId = null): array
    {
        $out = ['overdue' => 0];
        foreach ($this->list([], $groupIds, $teacherId) as $p) {
            $out[$p['status']] = ($out[$p['status']] ?? 0) + 1;
            if ($p['deadline'] < date('Y-m-d') && in_array($p['status'], ['assigned', 'in_progress', 'returned'], true)) {
                $out['overdue']++;
            }
        }

        return $out;
    }
}
