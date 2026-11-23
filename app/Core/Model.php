<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight table gateway (not an ORM). Subclasses declare $table and $fillable;
 * only whitelisted columns ever reach SQL. Complex queries belong in Repositories.
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    /** @var list<string> Columns allowed for insert/update and filtering. */
    protected static array $fillable = [];

    /** Soft-delete column name or null. */
    protected static ?string $softDelete = null;

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?%s LIMIT 1',
            static::table(),
            Database::identifier(static::$primaryKey),
            static::softDeleteClause()
        );

        return Database::fetch($sql, [$id]);
    }

    /** @return array<string, mixed>|null */
    public static function findBy(string $column, mixed $value): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?%s LIMIT 1',
            static::table(),
            static::column($column),
            static::softDeleteClause()
        );

        return Database::fetch($sql, [$value]);
    }

    /**
     * @param array<string, mixed> $where column => value (AND)
     * @return list<array<string, mixed>>
     */
    public static function where(array $where = [], string $orderBy = 'id', string $direction = 'ASC', ?int $limit = null): array
    {
        [$clause, $params] = static::buildWhere($where);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sql = sprintf(
            'SELECT * FROM %s%s ORDER BY %s %s',
            static::table(),
            $clause,
            static::column($orderBy),
            $direction
        );
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        return Database::fetchAll($sql, $params);
    }

    /** @param array<string, mixed> $where */
    public static function count(array $where = []): int
    {
        [$clause, $params] = static::buildWhere($where);

        return (int) Database::fetchColumn('SELECT COUNT(*) FROM ' . static::table() . $clause, $params);
    }

    /**
     * Server-side pagination.
     *
     * @param array<string, mixed> $where
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function paginate(int $page = 1, int $perPage = 20, array $where = [], string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $perPage = max(1, min(100, $perPage));
        $total = static::count($where);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        [$clause, $params] = static::buildWhere($where);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sql = sprintf(
            'SELECT * FROM %s%s ORDER BY %s %s LIMIT %d OFFSET %d',
            static::table(),
            $clause,
            static::column($orderBy),
            $direction,
            $perPage,
            ($page - 1) * $perPage
        );

        return [
            'items' => Database::fetchAll($sql, $params),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        $data = static::filterFillable($data);
        $columns = array_map([Database::class, 'identifier'], array_keys($data));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::table(),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($data), '?'))
        );
        Database::query($sql, array_values($data));

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): int
    {
        $data = static::filterFillable($data);
        if ($data === []) {
            return 0;
        }
        $sets = array_map(static fn (string $c): string => Database::identifier($c) . ' = ?', array_keys($data));
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            static::table(),
            implode(', ', $sets),
            Database::identifier(static::$primaryKey)
        );

        return Database::execute($sql, [...array_values($data), $id]);
    }

    public static function delete(int $id): int
    {
        if (static::$softDelete !== null) {
            $sql = sprintf(
                'UPDATE %s SET %s = NOW() WHERE %s = ?',
                static::table(),
                Database::identifier(static::$softDelete),
                Database::identifier(static::$primaryKey)
            );

            return Database::execute($sql, [$id]);
        }

        $sql = sprintf('DELETE FROM %s WHERE %s = ?', static::table(), Database::identifier(static::$primaryKey));

        return Database::execute($sql, [$id]);
    }

    protected static function table(): string
    {
        return Database::identifier(static::$table);
    }

    protected static function column(string $column): string
    {
        $allowed = array_merge(static::$fillable, [static::$primaryKey, 'created_at', 'updated_at']);
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Column [{$column}] is not allowed.");
        }

        return Database::identifier($column);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function filterFillable(array $data): array
    {
        return array_intersect_key($data, array_flip(static::$fillable));
    }

    /**
     * @param array<string, mixed> $where
     * @return array{0: string, 1: list<mixed>}
     */
    protected static function buildWhere(array $where): array
    {
        $parts = [];
        $params = [];
        foreach ($where as $column => $value) {
            if ($value === null) {
                $parts[] = static::column($column) . ' IS NULL';
                continue;
            }
            $parts[] = static::column($column) . ' = ?';
            $params[] = $value;
        }
        if (static::$softDelete !== null) {
            $parts[] = Database::identifier(static::$softDelete) . ' IS NULL';
        }

        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }

    private static function softDeleteClause(): string
    {
        return static::$softDelete === null ? '' : ' AND ' . Database::identifier(static::$softDelete) . ' IS NULL';
    }
}
