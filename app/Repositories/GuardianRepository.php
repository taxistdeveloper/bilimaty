<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Links between parents (users with the "parent" role) and students.
 */
final class GuardianRepository
{
    /** @return list<array<string, mixed>> children of the parent with group and study data */
    public function childrenOf(int $userId): array
    {
        return Database::fetchAll(
            'SELECT s.id, s.student_number, s.status, s.current_group_id, sg.relation, u.last_name, u.first_name, u.middle_name,
                    g.code AS group_code, g.course, sp.code AS specialty_code, sp.name_ru AS specialty_name_ru, sp.name_kk AS specialty_name_kk
             FROM student_guardians sg
             JOIN students s ON s.id = sg.student_id
             JOIN users u ON u.id = s.user_id
             LEFT JOIN `groups` g ON g.id = s.current_group_id
             LEFT JOIN specialties sp ON sp.id = g.specialty_id
             WHERE sg.user_id = ? ORDER BY u.last_name, u.first_name',
            [$userId]
        );
    }

    public function isGuardian(int $userId, int $studentId): bool
    {
        return Database::fetchColumn('SELECT 1 FROM student_guardians WHERE user_id = ? AND student_id = ?', [$userId, $studentId]) !== null;
    }

    /** @return list<array<string, mixed>> parents of the student */
    public function guardiansOf(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT u.id AS user_id, u.login, u.last_name, u.first_name, u.middle_name, u.phone, u.email, u.is_active, u.last_login_at, sg.relation
             FROM student_guardians sg JOIN users u ON u.id = sg.user_id
             WHERE sg.student_id = ? ORDER BY sg.created_at',
            [$studentId]
        );
    }

    public function link(int $studentId, int $userId, string $relation, ?int $createdBy): void
    {
        Database::execute(
            'INSERT INTO student_guardians (student_id, user_id, relation, created_by) VALUES (?, ?, ?, ?)',
            [$studentId, $userId, $relation, $createdBy]
        );
    }

    public function unlink(int $studentId, int $userId): void
    {
        Database::execute('DELETE FROM student_guardians WHERE student_id = ? AND user_id = ?', [$studentId, $userId]);
    }

    public function count(int $studentId): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM student_guardians WHERE student_id = ?', [$studentId]);
    }

    /** Active user with the "parent" role found by login (null if absent). @return array<string, mixed>|null */
    public function parentByLogin(string $login): ?array
    {
        return Database::fetch(
            "SELECT u.id, u.login, u.last_name, u.first_name, u.middle_name,
                    EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.slug = 'parent') AS is_parent
             FROM users u WHERE u.login = ? AND u.deleted_at IS NULL",
            [$login]
        );
    }
}
