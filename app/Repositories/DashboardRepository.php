<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Aggregated read-only queries for the dashboard.
 */
final class DashboardRepository
{
    /**
     * Counters limited to the user's data scope (a curator sees their groups only).
     *
     * @return array{students: int, teachers: int, groups: int, departments: int}
     */
    public function counters(): array
    {
        [$groupSql, $groupParams] = AccessScope::condition('current_group_id', AccessScope::groupIds());
        [$groupIdSql, $groupIdParams] = AccessScope::condition('id', AccessScope::groupIds());
        [$teacherSql, $teacherParams] = AccessScope::condition('department_id', AccessScope::departmentIds());
        [$departmentSql, $departmentParams] = AccessScope::condition('id', AccessScope::departmentIds());

        $row = Database::fetch(
            "SELECT
                (SELECT COUNT(*) FROM students WHERE status = 'active' AND {$groupSql}) AS students,
                (SELECT COUNT(*) FROM teachers WHERE status = 'active' AND {$teacherSql}) AS teachers,
                (SELECT COUNT(*) FROM `groups` WHERE status = 'active' AND {$groupIdSql}) AS `groups`,
                (SELECT COUNT(*) FROM departments WHERE is_active = 1 AND {$departmentSql}) AS departments",
            [...$groupParams, ...$teacherParams, ...$groupIdParams, ...$departmentParams]
        ) ?? [];

        return [
            'students' => (int) ($row['students'] ?? 0),
            'teachers' => (int) ($row['teachers'] ?? 0),
            'groups' => (int) ($row['groups'] ?? 0),
            'departments' => (int) ($row['departments'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|null */
    public function activeAcademicYear(): ?array
    {
        return Database::fetch("SELECT * FROM academic_years WHERE status = 'active' LIMIT 1");
    }

    /** @return list<array<string, mixed>> */
    public function semesters(int $academicYearId): array
    {
        return Database::fetchAll(
            'SELECT id, number, start_date, end_date FROM semesters WHERE academic_year_id = ? ORDER BY number',
            [$academicYearId]
        );
    }

    /** Active students per department for the chart. @return list<array<string, mixed>> */
    public function studentsByDepartment(): array
    {
        [$scopeSql, $params] = AccessScope::condition('s.current_group_id', AccessScope::groupIds());

        return Database::fetchAll(
            "SELECT d.name_ru, d.name_kk, COUNT(s.id) AS total
             FROM departments d
             LEFT JOIN specialties sp ON sp.department_id = d.id
             LEFT JOIN `groups` g ON g.specialty_id = sp.id AND g.status = 'active'
             LEFT JOIN students s ON s.current_group_id = g.id AND s.status = 'active' AND {$scopeSql}
             WHERE d.is_active = 1
             GROUP BY d.id, d.name_ru, d.name_kk
             ORDER BY d.name_ru",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function recentAudit(int $limit = 8): array
    {
        return Database::fetchAll(
            'SELECT a.action, a.ip_address, a.created_at, u.last_name, u.first_name, u.middle_name
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT ' . max(1, min(50, $limit))
        );
    }
}
