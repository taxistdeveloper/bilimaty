<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AuditRepository
{
    /**
     * @param array{action?: string, user?: string, from?: string, to?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = [];
        $params = [];
        if (($filters['action'] ?? '') !== '') {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        if (($filters['user'] ?? '') !== '') {
            $where[] = '(u.login LIKE ? OR CONCAT_WS(\' \', u.last_name, u.first_name, u.middle_name) LIKE ?)';
            $like = '%' . addcslashes((string) $filters['user'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $from = ' FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '');
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . $from, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $items = Database::fetchAll(
            'SELECT a.id, a.action, a.entity_type, a.entity_id, a.old_values, a.new_values, a.ip_address,
                    a.user_agent, a.created_at, u.login, u.last_name, u.first_name, u.middle_name'
            . $from . ' ORDER BY a.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_column(Database::fetchAll('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');
    }
}
