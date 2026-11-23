<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Academic calendar events. An event targets the whole college (course and group NULL),
 * one course, or one group; the audience filter returns what a given group/course sees.
 */
final class CalendarRepository
{
    private const COLUMNS = 'e.*, g.code AS group_code';

    /**
     * @param array{group_id?: ?int, course?: ?int, type?: string} $audience
     * @return list<array<string, mixed>>
     */
    public function events(int $yearId, array $audience = [], ?string $from = null, ?string $to = null): array
    {
        $where = ['e.academic_year_id = ?'];
        $params = [$yearId];

        $groupId = $audience['group_id'] ?? null;
        $course = $audience['course'] ?? null;
        if ($groupId !== null) {
            // A group sees college-wide events, events of its course and its own events.
            $where[] = '((e.course IS NULL AND e.group_id IS NULL) OR e.group_id = ? OR (e.group_id IS NULL AND e.course = ?))';
            array_push($params, $groupId, (int) Database::fetchColumn('SELECT course FROM `groups` WHERE id = ?', [$groupId]));
        } elseif ($course !== null) {
            $where[] = '((e.course IS NULL AND e.group_id IS NULL) OR (e.group_id IS NULL AND e.course = ?))';
            $params[] = $course;
        }
        if (($audience['type'] ?? '') !== '') {
            $where[] = 'e.type = ?';
            $params[] = $audience['type'];
        }
        if ($from !== null && $to !== null) {
            $where[] = 'e.start_date <= ? AND e.end_date >= ?';
            array_push($params, $to, $from);
        }

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM academic_calendar_events e LEFT JOIN `groups` g ON g.id = e.group_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY e.start_date, e.end_date, e.id',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT ' . self::COLUMNS . ', y.status AS year_status, y.start_date AS year_start, y.end_date AS year_end
             FROM academic_calendar_events e JOIN academic_years y ON y.id = e.academic_year_id
             LEFT JOIN `groups` g ON g.id = e.group_id WHERE e.id = ?',
            [$id]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO academic_calendar_events (academic_year_id, type, title_ru, title_kk, start_date, end_date, course, group_id, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['academic_year_id'], $data['type'], $data['title_ru'], $data['title_kk'], $data['start_date'], $data['end_date'],
             $data['course'], $data['group_id'], $data['note'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE academic_calendar_events SET type = ?, title_ru = ?, title_kk = ?, start_date = ?, end_date = ?,
                    course = ?, group_id = ?, note = ? WHERE id = ?',
            [$data['type'], $data['title_ru'], $data['title_kk'], $data['start_date'], $data['end_date'],
             $data['course'], $data['group_id'], $data['note'], $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM academic_calendar_events WHERE id = ?', [$id]);
    }

    public function existsOn(int $yearId, string $type, string $date): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM academic_calendar_events WHERE academic_year_id = ? AND type = ? AND start_date = ?',
            [$yearId, $type, $date]
        ) > 0;
    }

    /** @return array<string, mixed>|null current group and course of a student account */
    public function studentPlacement(int $userId): ?array
    {
        return Database::fetch(
            "SELECT s.current_group_id AS group_id, g.course, g.code FROM students s
             JOIN `groups` g ON g.id = s.current_group_id WHERE s.user_id = ? AND s.status = 'active'",
            [$userId]
        );
    }
}
