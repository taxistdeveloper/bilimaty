<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Generic CRUD for the reference dictionaries described in config/dictionaries.php.
 * Table and column names come only from that config and are quoted via Database::identifier().
 */
final class DictionaryRepository
{
    /** @param array<string, mixed> $definition */
    public function __construct(private readonly array $definition)
    {
    }

    /**
     * @param array<string, mixed> $filters  ['q' => string, '<select field>' => id|value]
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $table = Database::identifier($this->definition['table']);
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $parts = [];
            foreach ($this->definition['search'] as $column) {
                $parts[] = Database::identifier($column) . ' LIKE ?';
                $params[] = $like;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach ($this->definition['fields'] as $name => $field) {
            if (!empty($field['filter']) && ($filters[$name] ?? '') !== '') {
                $where[] = Database::identifier($name) . ' = ?';
                $params[] = $filters[$name];
            }
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn("SELECT COUNT(*) FROM {$table}{$whereSql}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $order = Database::identifier($this->definition['order']);

        $items = Database::fetchAll(
            "SELECT * FROM {$table}{$whereSql} ORDER BY is_active DESC, {$order}, id LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM ' . Database::identifier($this->definition['table']) . ' WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data = $this->onlyFields($data);
        $columns = implode(', ', array_map([Database::class, 'identifier'], array_keys($data)));
        Database::execute(
            'INSERT INTO ' . Database::identifier($this->definition['table']) . " ({$columns}) VALUES ("
            . implode(', ', array_fill(0, count($data), '?')) . ')',
            array_values($data)
        );

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $data = $this->onlyFields($data);
        $sets = implode(', ', array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data)));
        Database::execute(
            'UPDATE ' . Database::identifier($this->definition['table']) . " SET {$sets} WHERE id = ?",
            [...array_values($data), $id]
        );
    }

    public function delete(int $id): void
    {
        Database::execute('DELETE FROM ' . Database::identifier($this->definition['table']) . ' WHERE id = ?', [$id]);
    }

    /** Number of records in other tables that reference this one. */
    public function usageCount(int $id): int
    {
        $total = 0;
        foreach ($this->definition['usage'] as [$table, $column]) {
            $total += (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM ' . Database::identifier($table) . ' WHERE ' . Database::identifier($column) . ' = ?',
                [$id]
            );
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $values column => value
     */
    public function existsTogether(array $values, ?int $ignoreId): bool
    {
        $where = [];
        $params = [];
        foreach ($values as $column => $value) {
            $where[] = Database::identifier($column) . ' = ?';
            $params[] = $value;
        }
        if ($ignoreId !== null) {
            $where[] = 'id <> ?';
            $params[] = $ignoreId;
        }

        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM ' . Database::identifier($this->definition['table']) . ' WHERE ' . implode(' AND ', $where),
            $params
        ) > 0;
    }

    public function count(): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM ' . Database::identifier($this->definition['table']));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function onlyFields(array $data): array
    {
        return array_intersect_key($data, $this->definition['fields']);
    }
}
