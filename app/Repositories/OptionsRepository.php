<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\AccessScope;

/**
 * Small id => label lists for <select> controls, filtered by the user's data scope where relevant.
 */
final class OptionsRepository
{
    /** @return array<int, string> */
    public function departments(bool $scoped = false, bool $activeOnly = false): array
    {
        [$scopeSql, $params] = $scoped ? AccessScope::condition('id', AccessScope::departmentIds()) : ['1 = 1', []];
        $rows = Database::fetchAll(
            "SELECT id, code, name_ru, name_kk FROM departments WHERE {$scopeSql}"
            . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY name_ru',
            $params
        );

        return $this->map($rows, static fn (array $r): string => localized($r));
    }

    /** @return array<int, string> */
    public function specialties(bool $scoped = false, bool $activeOnly = false): array
    {
        [$scopeSql, $params] = $scoped ? AccessScope::condition('department_id', AccessScope::departmentIds()) : ['1 = 1', []];
        $rows = Database::fetchAll(
            "SELECT id, code, name_ru, name_kk FROM specialties WHERE {$scopeSql}"
            . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY code',
            $params
        );

        return $this->map($rows, static fn (array $r): string => $r['code'] . ' — ' . localized($r));
    }

    /** @return list<array{id: int, specialty_id: int, label: string}> */
    public function qualifications(?int $specialtyId = null): array
    {
        $rows = Database::fetchAll(
            'SELECT id, specialty_id, code, name_ru, name_kk FROM qualifications WHERE is_active = 1'
            . ($specialtyId !== null ? ' AND specialty_id = ?' : '') . ' ORDER BY code',
            $specialtyId !== null ? [$specialtyId] : []
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'specialty_id' => (int) $r['specialty_id'],
            'label' => $r['code'] . ' — ' . localized($r),
        ], $rows);
    }

    /** @return array<int, string> teachers as "Last F. M." */
    public function teachers(bool $scoped = false): array
    {
        [$scopeSql, $params] = $scoped ? AccessScope::condition('t.department_id', AccessScope::departmentIds()) : ['1 = 1', []];
        $rows = Database::fetchAll(
            "SELECT t.id, u.last_name, u.first_name, u.middle_name FROM teachers t JOIN users u ON u.id = t.user_id
             WHERE t.status <> 'dismissed' AND u.deleted_at IS NULL AND {$scopeSql}
             ORDER BY u.last_name, u.first_name",
            $params
        );

        return $this->map($rows, static fn (array $r): string => full_name($r, true));
    }

    /** @return array<int, string> groups visible to the user */
    public function groups(bool $activeOnly = true): array
    {
        [$scopeSql, $params] = AccessScope::condition('id', AccessScope::groupIds());
        $rows = Database::fetchAll(
            "SELECT id, code FROM `groups` WHERE {$scopeSql}" . ($activeOnly ? " AND status = 'active'" : '') . ' ORDER BY code',
            $params
        );

        return $this->map($rows, static fn (array $r): string => (string) $r['code']);
    }

    /** @return array<int, string> */
    public function academicYears(): array
    {
        $rows = Database::fetchAll("SELECT id, title, status FROM academic_years WHERE status <> 'archived' ORDER BY start_date");

        return $this->map($rows, static fn (array $r): string => str_replace('-', '–', (string) $r['title']));
    }

    public function activeAcademicYearId(): ?int
    {
        $id = Database::fetchColumn("SELECT id FROM academic_years WHERE status = 'active' LIMIT 1");

        return $id === null ? null : (int) $id;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function map(array $rows, callable $label): array
    {
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = $label($row);
        }

        return $options;
    }
}
