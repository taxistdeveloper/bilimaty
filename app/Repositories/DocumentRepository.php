<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Config;
use App\Core\Database;

/**
 * Document requests, their history, request types and generated certificates.
 */
final class DocumentRepository
{
    private const COLUMNS = "r.*, t.code AS type_code, t.name_ru AS type_name_ru, t.name_kk AS type_name_kk, t.generator, t.needs_purpose, t.allow_attachment,
        t.description_ru AS type_description_ru, t.description_kk AS type_description_kk,
        s.student_number, s.user_id AS student_user_id, s.current_group_id, u.last_name, u.first_name, u.middle_name, u.locale AS student_locale,
        g.code AS group_code, sp.department_id,
        au.last_name AS assignee_last_name, au.first_name AS assignee_first_name, au.middle_name AS assignee_middle_name";

    private const JOINS = ' FROM document_requests r
        JOIN document_types t ON t.id = r.type_id
        JOIN students s ON s.id = r.student_id
        JOIN users u ON u.id = s.user_id
        LEFT JOIN `groups` g ON g.id = s.current_group_id
        LEFT JOIN specialties sp ON sp.id = g.specialty_id
        LEFT JOIN users au ON au.id = r.assigned_to';

    // ---- types ---------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function types(bool $activeOnly = true): array
    {
        return Database::fetchAll('SELECT * FROM document_types' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
    }

    /** @return array<string, mixed>|null */
    public function type(int $id): ?array
    {
        return Database::fetch('SELECT * FROM document_types WHERE id = ?', [$id]);
    }

    // ---- requests ------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE r.id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function forStudent(int $studentId): array
    {
        return Database::fetchAll('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE r.student_id = ? ORDER BY r.id DESC', [$studentId]);
    }

    /**
     * Processing inbox (drafts are never shown to staff).
     *
     * @param array{status?: string, type?: ?int, q?: string, overdue?: bool, mine?: bool} $filters
     * @param list<int>|null $groups visible groups (null = all)
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function inbox(array $filters, ?array $groups, int $userId, int $page, int $perPage = 25): array
    {
        $where = ["r.status <> 'draft'"];
        $params = [];
        if ($groups !== null) {
            $where[] = $groups === [] ? '1 = 0' : 's.current_group_id IN (' . implode(',', array_fill(0, count($groups), '?')) . ')';
            $params = array_merge($params, $groups);
        }
        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $where[] = "r.status IN ('submitted','in_review','needs_info','approved')";
        } elseif (in_array($status, ['submitted', 'in_review', 'needs_info', 'approved', 'rejected', 'completed'], true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['type'])) {
            $where[] = 'r.type_id = ?';
            $params[] = (int) $filters['type'];
        }
        if (!empty($filters['overdue'])) {
            $where[] = "r.due_date < CURDATE() AND r.status IN ('submitted','in_review','needs_info')";
        }
        if (!empty($filters['mine'])) {
            $where[] = 'r.assigned_to = ?';
            $params[] = $userId;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(r.number LIKE ? OR s.student_number LIKE ? OR CONCAT_WS(" ", u.last_name, u.first_name, u.middle_name) LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $sql = self::JOINS . ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . $sql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = Database::fetchAll(
            'SELECT ' . self::COLUMNS . $sql . " ORDER BY FIELD(r.status, 'submitted', 'needs_info', 'in_review', 'approved', 'completed', 'rejected'), r.due_date IS NULL, r.due_date, r.id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Number of requests per status in scope (for the inbox tabs).
     *
     * @param list<int>|null $groups
     * @return array<string, int>
     */
    public function counts(?array $groups): array
    {
        $where = "r.status <> 'draft'";
        $params = [];
        if ($groups !== null) {
            $where .= $groups === [] ? ' AND 1 = 0' : ' AND s.current_group_id IN (' . implode(',', array_fill(0, count($groups), '?')) . ')';
            $params = $groups;
        }
        $out = [];
        foreach (Database::fetchAll("SELECT r.status, COUNT(*) AS n FROM document_requests r JOIN students s ON s.id = r.student_id WHERE {$where} GROUP BY r.status", $params) as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        $out['overdue'] = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM document_requests r JOIN students s ON s.id = r.student_id WHERE {$where} AND r.due_date < CURDATE() AND r.status IN ('submitted','in_review','needs_info')",
            $params
        );

        return $out;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        Database::execute(
            'INSERT INTO document_requests (type_id, student_id, purpose, copies, details, payload, attachment_path, attachment_name, attachment_mime, attachment_size, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['type_id'], $data['student_id'], $data['purpose'], $data['copies'], $data['details'], $data['payload'],
                $data['attachment_path'], $data['attachment_name'], $data['attachment_mime'], $data['attachment_size'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data only the given columns are updated */
    public function update(int $id, array $data): void
    {
        $allowed = ['purpose', 'copies', 'details', 'payload', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
            'status', 'number', 'assigned_to', 'transcript_id', 'submitted_at', 'due_date', 'decided_at', 'decided_by', 'decision_comment', 'completed_at'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data)));
        Database::execute("UPDATE document_requests SET {$set} WHERE id = ?", [...array_values($data), $id]);
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM document_requests WHERE id = ?', [$id]);
    }

    /** Next registration number for the year (call inside a transaction). */
    public function nextNumber(string $table, string $prefix): string
    {
        $table = $table === 'generated_documents' ? 'generated_documents' : 'document_requests';
        $last = Database::fetchColumn("SELECT MAX(number) FROM {$table} WHERE number LIKE ? FOR UPDATE", [$prefix . '%']);

        return $prefix . str_pad((string) (($last !== null ? (int) substr((string) $last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ---- history -------------------------------------------------------------

    public function log(int $requestId, string $kind, ?string $from, ?string $to, ?string $comment, ?int $userId, bool $internal = false): void
    {
        Database::execute(
            'INSERT INTO document_request_history (request_id, kind, from_status, to_status, comment, is_internal, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$requestId, $kind, $from, $to, $comment, $internal ? 1 : 0, $userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function history(int $requestId, bool $withInternal): array
    {
        return Database::fetchAll(
            'SELECT h.*, u.last_name, u.first_name, u.middle_name FROM document_request_history h LEFT JOIN users u ON u.id = h.user_id
             WHERE h.request_id = ?' . ($withInternal ? '' : ' AND h.is_internal = 0') . ' ORDER BY h.id',
            [$requestId]
        );
    }

    // ---- recipients ----------------------------------------------------------

    /**
     * Staff who process requests of this student: users holding documents.process through a role
     * that is college-wide, or heads of the student's department.
     *
     * @return list<int>
     */
    public function processors(int $studentId): array
    {
        $collegeWide = (array) Config::get('permissions.college_wide_roles', []);
        $roles = $collegeWide === [] ? '0' : implode(',', array_fill(0, count($collegeWide), '?'));
        $rows = Database::fetchAll(
            "SELECT DISTINCT u.id FROM users u
             JOIN user_roles ur ON ur.user_id = u.id JOIN roles ro ON ro.id = ur.role_id
             JOIN role_permissions rp ON rp.role_id = ro.id JOIN permissions p ON p.id = rp.permission_id AND p.slug = 'documents.process'
             WHERE u.is_active = 1 AND u.deleted_at IS NULL AND (
                ro.slug IN ({$roles})
                OR (ro.slug = 'department_head' AND EXISTS (
                    SELECT 1 FROM teachers t JOIN departments d ON d.head_teacher_id = t.id
                    JOIN students s ON s.id = ? JOIN `groups` g ON g.id = s.current_group_id JOIN specialties sp ON sp.id = g.specialty_id
                    WHERE t.user_id = u.id AND d.id = sp.department_id)))",
            [...$collegeWide, $studentId]
        );

        return array_map('intval', array_column($rows, 'id'));
    }

    // ---- generated documents -------------------------------------------------

    /** @param array<string, mixed> $data */
    public function createDocument(array $data): int
    {
        Database::execute(
            'INSERT INTO generated_documents (request_id, student_id, kind, number, snapshot, fingerprint, verification_code, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['request_id'], $data['student_id'], $data['kind'], $data['number'], $data['snapshot'], $data['fingerprint'], $data['verification_code'], $data['issued_by']]
        );

        return Database::lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function documentOfRequest(int $requestId): ?array
    {
        return Database::fetch('SELECT * FROM generated_documents WHERE request_id = ?', [$requestId]);
    }

    public function revokeDocument(int $id, string $reason, ?int $userId): void
    {
        Database::execute("UPDATE generated_documents SET status = 'revoked', revoked_at = NOW(), revoked_by = ?, revoke_reason = ? WHERE id = ? AND status = 'valid'", [$userId, $reason, $id]);
    }

    /** Facts for the public check — no personal data. @return array<string, mixed>|null */
    public function verifyDocument(string $code): ?array
    {
        return Database::fetch('SELECT kind, number, status, issued_at, revoked_at, fingerprint FROM generated_documents WHERE verification_code = ?', [$code]);
    }
}
