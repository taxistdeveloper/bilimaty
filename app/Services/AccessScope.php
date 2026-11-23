<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

/**
 * Data scope of the current user (record-level access for people and groups).
 *
 *   null  — the whole college (college-wide roles);
 *   list  — only these ids (may be empty = nothing).
 *
 * Department head: departments they head (departments.head_teacher_id).
 * Curator: groups they curate (groups.curator_teacher_id).
 * Results are cached for the request.
 */
final class AccessScope
{
    private static ?bool $collegeWide = null;

    /** @var list<int>|null */
    private static ?array $departments = null;

    /** @var list<int>|null */
    private static ?array $groups = null;

    private static bool $resolved = false;

    public static function isCollegeWide(): bool
    {
        if (self::$collegeWide === null) {
            self::$collegeWide = Auth::hasRole(...(array) Config::get('permissions.college_wide_roles', [Auth::SUPER_ADMIN]));
        }

        return self::$collegeWide;
    }

    /** Teacher profile id of the current user, if any. */
    public static function teacherId(): ?int
    {
        $id = Database::fetchColumn('SELECT id FROM teachers WHERE user_id = ?', [(int) Auth::id()]);

        return $id === null ? null : (int) $id;
    }

    /** @return list<int>|null departments the user may work with (null = all) */
    public static function departmentIds(): ?array
    {
        self::resolve();

        return self::$departments;
    }

    /** @return list<int>|null groups the user may work with (null = all) */
    public static function groupIds(): ?array
    {
        self::resolve();

        return self::$groups;
    }

    public static function allowsDepartment(?int $departmentId): bool
    {
        $scope = self::departmentIds();

        return $scope === null || ($departmentId !== null && in_array($departmentId, $scope, true));
    }

    public static function allowsGroup(?int $groupId): bool
    {
        $scope = self::groupIds();

        return $scope === null || ($groupId !== null && in_array($groupId, $scope, true));
    }

    /**
     * SQL condition limiting $column to the given scope.
     *
     * @param list<int>|null $ids
     * @return array{0: string, 1: list<int>} [sql, params]; "1 = 1" for unrestricted, "1 = 0" for empty
     */
    public static function condition(string $column, ?array $ids): array
    {
        if ($ids === null) {
            return ['1 = 1', []];
        }
        if ($ids === []) {
            return ['1 = 0', []];
        }

        return [$column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }

    public static function reset(): void
    {
        self::$collegeWide = null;
        self::$departments = null;
        self::$groups = null;
        self::$resolved = false;
    }

    private static function resolve(): void
    {
        if (self::$resolved) {
            return;
        }
        self::$resolved = true;

        if (self::isCollegeWide()) {
            self::$departments = null;
            self::$groups = null;

            return;
        }

        $teacherId = self::teacherId();
        if ($teacherId === null) {
            self::$departments = [];
            self::$groups = [];

            return;
        }

        $departments = Auth::hasRole('department_head')
            ? array_map('intval', array_column(
                Database::fetchAll('SELECT id FROM departments WHERE head_teacher_id = ?', [$teacherId]),
                'id'
            ))
            : [];

        $groups = array_map('intval', array_column(
            Database::fetchAll('SELECT id FROM `groups` WHERE curator_teacher_id = ?', [$teacherId]),
            'id'
        ));
        if ($departments !== []) {
            $groups = array_merge($groups, array_map('intval', array_column(Database::fetchAll(
                'SELECT g.id FROM `groups` g JOIN specialties s ON s.id = g.specialty_id
                 WHERE s.department_id IN (' . implode(',', array_fill(0, count($departments), '?')) . ')',
                $departments
            ), 'id')));
        }

        self::$departments = $departments;
        self::$groups = array_values(array_unique($groups));
    }
}
