<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Curricula (study plans) and their subject rows.
 */
final class CurriculumRepository
{
    private const COLUMNS = "c.*, s.code AS specialty_code, s.name_ru AS specialty_name_ru, s.name_kk AS specialty_name_kk,
        q.code AS qualification_code, q.name_ru AS qualification_name_ru, q.name_kk AS qualification_name_kk,
        a.last_name AS approver_last_name, a.first_name AS approver_first_name, a.middle_name AS approver_middle_name,
        (SELECT COALESCE(SUM(cs.hours_total), 0) FROM curriculum_subjects cs WHERE cs.curriculum_id = c.id) AS hours_total,
        (SELECT COUNT(*) FROM curriculum_subjects cs WHERE cs.curriculum_id = c.id) AS items_count,
        (SELECT COUNT(*) FROM `groups` g WHERE g.curriculum_id = c.id) AS groups_count";

    private const JOINS = ' FROM curricula c JOIN specialties s ON s.id = c.specialty_id
        LEFT JOIN qualifications q ON q.id = c.qualification_id LEFT JOIN users a ON a.id = c.approved_by';

    /**
     * @param array{specialty?: ?int, status?: string, year?: ?int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['specialty'])) {
            $where[] = 'c.specialty_id = ?';
            $params[] = (int) $filters['specialty'];
        }
        if (in_array($filters['status'] ?? '', ['draft', 'approved', 'archived'], true)) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['year'])) {
            $where[] = 'c.admission_year = ?';
            $params[] = (int) $filters['year'];
        }

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY c.status = 'archived', c.admission_year DESC, s.code",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE c.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function items(int $curriculumId): array
    {
        return Database::fetchAll(
            'SELECT cs.*, sb.code AS subject_code, sb.name_ru, sb.name_kk FROM curriculum_subjects cs
             JOIN subjects sb ON sb.id = cs.subject_id
             WHERE cs.curriculum_id = ? ORDER BY cs.semester, cs.sort_order, cs.module_code, sb.name_ru',
            [$curriculumId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $curriculumId, int $itemId): ?array
    {
        return Database::fetch('SELECT * FROM curriculum_subjects WHERE id = ? AND curriculum_id = ?', [$itemId, $curriculumId]);
    }

    /** @return list<array<string, mixed>> */
    public function groups(int $curriculumId): array
    {
        return Database::fetchAll('SELECT id, code, course, status FROM `groups` WHERE curriculum_id = ? ORDER BY code', [$curriculumId]);
    }

    /** @param array<string, mixed> $data */
    public function cohortExists(array $data, ?int $ignoreId): bool
    {
        $sql = 'SELECT COUNT(*) FROM curricula WHERE specialty_id = ? AND qualification_id <=> ? AND admission_year = ?
                AND study_form = ? AND base_education = ?';
        $params = [$data['specialty_id'], $data['qualification_id'], $data['admission_year'], $data['study_form'], $data['base_education']];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        return (int) Database::fetchColumn($sql, $params) > 0;
    }

    public function itemExists(int $curriculumId, int $subjectId, int $semester, ?int $ignoreId): bool
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM curriculum_subjects WHERE curriculum_id = ? AND subject_id = ? AND semester = ?'
            . ($ignoreId !== null ? ' AND id <> ?' : ''),
            $ignoreId !== null ? [$curriculumId, $subjectId, $semester, $ignoreId] : [$curriculumId, $subjectId, $semester]
        ) > 0;
    }

    public function maxItemSemester(int $curriculumId): int
    {
        return (int) Database::fetchColumn('SELECT COALESCE(MAX(semester), 0) FROM curriculum_subjects WHERE curriculum_id = ?', [$curriculumId]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = ['specialty_id', 'qualification_id', 'title', 'admission_year', 'study_form', 'base_education', 'semesters_count', 'note'];
        Database::execute(
            'INSERT INTO curricula (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_map(static fn (string $c) => $data[$c] ?? null, $columns)
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE curricula SET specialty_id = ?, qualification_id = ?, title = ?, admission_year = ?, study_form = ?,
                    base_education = ?, semesters_count = ?, note = ? WHERE id = ?',
            [$data['specialty_id'], $data['qualification_id'], $data['title'], $data['admission_year'], $data['study_form'],
             $data['base_education'], $data['semesters_count'], $data['note'], $id]
        );
    }

    public function setStatus(int $id, string $status, ?int $userId): void
    {
        Database::execute(
            'UPDATE curricula SET status = ?, approved_at = IF(? = \'approved\', NOW(), approved_at),
                    approved_by = IF(? = \'approved\', ?, approved_by) WHERE id = ?',
            [$status, $status, $status, $userId, $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM curricula WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function saveItem(int $curriculumId, ?int $itemId, array $data): int
    {
        $values = [$data['subject_id'], $data['module_code'], $data['semester'], $data['hours_total'], $data['hours_theory'],
            $data['hours_practice'], $data['hours_lab'], $data['credits'], $data['control_form']];
        if ($itemId === null) {
            Database::execute(
                'INSERT INTO curriculum_subjects (curriculum_id, subject_id, module_code, semester, hours_total, hours_theory,
                        hours_practice, hours_lab, credits, control_form) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$curriculumId, ...$values]
            );

            return Database::lastInsertId();
        }
        Database::execute(
            'UPDATE curriculum_subjects SET subject_id = ?, module_code = ?, semester = ?, hours_total = ?, hours_theory = ?,
                    hours_practice = ?, hours_lab = ?, credits = ?, control_form = ? WHERE id = ? AND curriculum_id = ?',
            [...$values, $itemId, $curriculumId]
        );

        return $itemId;
    }

    public function deleteItem(int $curriculumId, int $itemId): void
    {
        Database::execute('DELETE FROM curriculum_subjects WHERE id = ? AND curriculum_id = ?', [$itemId, $curriculumId]);
    }

    /** Copy all subject rows of one plan into another. */
    public function copyItems(int $fromId, int $toId): void
    {
        Database::execute(
            'INSERT INTO curriculum_subjects (curriculum_id, subject_id, module_code, semester, hours_total, hours_theory,
                    hours_practice, hours_lab, credits, control_form, sort_order)
             SELECT ?, subject_id, module_code, semester, hours_total, hours_theory, hours_practice, hours_lab, credits,
                    control_form, sort_order FROM curriculum_subjects WHERE curriculum_id = ?',
            [$toId, $fromId]
        );
    }

    /** @return array<int, string> plans that may be attached to a group of the given specialty */
    public function optionsForSpecialty(?int $specialtyId = null): array
    {
        $rows = Database::fetchAll(
            "SELECT id, specialty_id, title, admission_year, status FROM curricula WHERE status <> 'archived'"
            . ($specialtyId !== null ? ' AND specialty_id = ?' : '') . ' ORDER BY admission_year DESC, title',
            $specialtyId !== null ? [$specialtyId] : []
        );

        return array_column($rows, null, 'id');
    }
}
