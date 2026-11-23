<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class GradingSchemeRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $schemes = Database::fetchAll(
            "SELECT gs.*, (SELECT COUNT(*) FROM teacher_subject_assignments a WHERE a.grading_scheme_id = gs.id) AS journals_count
             FROM grading_schemes gs ORDER BY gs.status = 'archived', gs.title, gs.version DESC"
        );
        foreach ($schemes as &$scheme) {
            $scheme['components'] = $this->components((int) $scheme['id']);
        }

        return $schemes;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $scheme = Database::fetch(
            'SELECT gs.*, (SELECT COUNT(*) FROM teacher_subject_assignments a WHERE a.grading_scheme_id = gs.id) AS journals_count
             FROM grading_schemes gs WHERE gs.id = ?',
            [$id]
        );
        if ($scheme !== null) {
            $scheme['components'] = $this->components($id);
        }

        return $scheme;
    }

    /** @return list<array<string, mixed>> components with type data, in display order */
    public function components(int $schemeId): array
    {
        return Database::fetchAll(
            'SELECT c.*, t.code, t.name_ru, t.name_kk, t.short_ru, t.short_kk, t.source
             FROM grading_scheme_components c JOIN assessment_types t ON t.id = c.assessment_type_id
             WHERE c.grading_scheme_id = ? ORDER BY t.sort_order, c.sort_order, t.id',
            [$schemeId]
        );
    }

    /** @return list<array<string, mixed>> types that can be a component (not the final itself) */
    public function componentTypes(): array
    {
        return Database::fetchAll("SELECT * FROM assessment_types WHERE is_active = 1 AND source <> 'computed_final' ORDER BY sort_order, id");
    }

    /** @return array<int, string> active schemes for selects */
    public function activeOptions(): array
    {
        $options = [];
        foreach (Database::fetchAll("SELECT id, title, version FROM grading_schemes WHERE status = 'active' ORDER BY title, version DESC") as $row) {
            $options[(int) $row['id']] = $row['title'] . ' (v' . $row['version'] . ')';
        }

        return $options;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO grading_schemes (title, version, parent_id, grade_min, grade_max, final_decimals, pass_threshold, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['title'], $data['version'] ?? 1, $data['parent_id'] ?? null, $data['grade_min'], $data['grade_max'],
             $data['final_decimals'], $data['pass_threshold'], $data['note']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE grading_schemes SET title = ?, grade_min = ?, grade_max = ?, final_decimals = ?, pass_threshold = ?, note = ? WHERE id = ?',
            [$data['title'], $data['grade_min'], $data['grade_max'], $data['final_decimals'], $data['pass_threshold'], $data['note'], $id]
        );
    }

    /** @param array<int, string> $weights assessment_type_id => weight */
    public function replaceComponents(int $schemeId, array $weights): void
    {
        Database::execute('DELETE FROM grading_scheme_components WHERE grading_scheme_id = ?', [$schemeId]);
        $order = 0;
        foreach ($weights as $typeId => $weight) {
            Database::execute(
                'INSERT INTO grading_scheme_components (grading_scheme_id, assessment_type_id, weight, sort_order) VALUES (?, ?, ?, ?)',
                [$schemeId, $typeId, $weight, $order++]
            );
        }
    }

    public function setStatus(int $id, string $status, ?int $userId): void
    {
        Database::execute(
            "UPDATE grading_schemes SET status = ?, approved_by = IF(? = 'active', ?, approved_by), approved_at = IF(? = 'active', NOW(), approved_at) WHERE id = ?",
            [$status, $status, $userId, $status, $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute("DELETE FROM grading_schemes WHERE id = ? AND status = 'draft'", [$id]);
    }
}
