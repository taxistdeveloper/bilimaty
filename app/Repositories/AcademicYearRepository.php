<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AcademicYearRepository
{
    /** @return list<array<string, mixed>> years with semesters and usage counters */
    public function allWithStats(): array
    {
        $years = Database::fetchAll(
            'SELECT y.*,
                    (SELECT COUNT(*) FROM `groups` g WHERE g.academic_year_id = y.id) AS groups_count,
                    (SELECT COUNT(*) FROM academic_calendar_events e WHERE e.academic_year_id = y.id) AS events_count
             FROM academic_years y ORDER BY y.start_date'
        );
        $semesters = $this->semestersByYear();
        foreach ($years as &$year) {
            $year['semesters'] = $semesters[(int) $year['id']] ?? [];
        }

        return $years;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $year = Database::fetch('SELECT * FROM academic_years WHERE id = ?', [$id]);
        if ($year !== null) {
            $year['semesters'] = $this->semestersByYear($id)[$id] ?? [];
        }

        return $year;
    }

    /** @return array<string, mixed>|null */
    public function active(): ?array
    {
        $id = Database::fetchColumn("SELECT id FROM academic_years WHERE status = 'active' LIMIT 1");

        return $id === null ? null : $this->find((int) $id);
    }

    public function titleTaken(string $title, ?int $ignoreId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM academic_years WHERE title = ?' . ($ignoreId !== null ? ' AND id <> ?' : ''),
            $ignoreId !== null ? [$title, $ignoreId] : [$title]
        ) > 0;
    }

    /** Overlap with another year's period (years must not overlap). */
    public function overlaps(string $start, string $end, ?int $ignoreId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM academic_years WHERE start_date <= ? AND end_date >= ?' . ($ignoreId !== null ? ' AND id <> ?' : ''),
            $ignoreId !== null ? [$end, $start, $ignoreId] : [$end, $start]
        ) > 0;
    }

    public function eventsOutside(int $yearId, string $start, string $end): int
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM academic_calendar_events WHERE academic_year_id = ? AND (start_date < ? OR end_date > ?)',
            [$yearId, $start, $end]
        );
    }

    public function create(string $title, string $start, string $end): int
    {
        Database::execute(
            "INSERT INTO academic_years (title, start_date, end_date, status) VALUES (?, ?, ?, 'planned')",
            [$title, $start, $end]
        );

        return Database::lastInsertId();
    }

    public function update(int $id, string $title, string $start, string $end): void
    {
        Database::execute('UPDATE academic_years SET title = ?, start_date = ?, end_date = ? WHERE id = ?', [$title, $start, $end, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE academic_years SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** Insert or update semester N of the year. */
    public function saveSemester(int $yearId, int $number, string $start, string $end): void
    {
        Database::execute(
            'INSERT INTO semesters (academic_year_id, number, start_date, end_date) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date)',
            [$yearId, $number, $start, $end]
        );
    }

    /** Groups, events or curricula cohorts attached to the year. */
    public function usageCount(int $id): int
    {
        return (int) Database::fetchColumn(
            'SELECT (SELECT COUNT(*) FROM `groups` WHERE academic_year_id = ?)
                  + (SELECT COUNT(*) FROM academic_calendar_events WHERE academic_year_id = ?)',
            [$id, $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM semesters WHERE academic_year_id = ?', [$id]);
        Database::execute("DELETE FROM academic_years WHERE id = ? AND status <> 'active'", [$id]);
    }

    /** @return array<int, list<array<string, mixed>>> */
    private function semestersByYear(?int $yearId = null): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM semesters' . ($yearId !== null ? ' WHERE academic_year_id = ?' : '') . ' ORDER BY academic_year_id, number',
            $yearId !== null ? [$yearId] : []
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['academic_year_id']][] = $row;
        }

        return $map;
    }
}
