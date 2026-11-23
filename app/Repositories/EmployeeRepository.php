<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Personnel files, personnel orders, leaves and professional development.
 */
final class EmployeeRepository
{
    private const COLUMNS = "e.*, u.login, u.email, u.phone, u.last_name, u.first_name, u.middle_name, u.is_active AS user_active, u.locale,
        d.name_ru AS department_name_ru, d.name_kk AS department_name_kk, t.id AS teacher_id,
        (SELECT MAX(tr.end_date) FROM hr_trainings tr WHERE tr.employee_id = e.id) AS last_training";

    private const JOINS = ' FROM employees e JOIN users u ON u.id = e.user_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN teachers t ON t.user_id = e.user_id';

    // ---- employees -----------------------------------------------------------

    /**
     * @param array{q?: string, department?: ?int, category?: string, status?: string} $filters
     * @param list<int>|null $departments scope (null = all)
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function paginate(array $filters, ?array $departments, int $page, int $perPage = 25): array
    {
        [$where, $params] = $this->where($filters, $departments);
        $sql = self::JOINS . ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::fetchColumn('SELECT COUNT(*)' . $sql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = Database::fetchAll('SELECT ' . self::COLUMNS . $sql . ' ORDER BY u.last_name, u.first_name LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array<string, mixed>> all matching rows (export) */
    public function all(array $filters, ?array $departments): array
    {
        [$where, $params] = $this->where($filters, $departments);

        return Database::fetchAll('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY u.last_name, u.first_name', $params);
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    private function where(array $filters, ?array $departments): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($departments !== null) {
            $where[] = $departments === [] ? '1 = 0' : 'e.department_id IN (' . implode(',', array_fill(0, count($departments), '?')) . ')';
            $params = array_merge($params, $departments);
        }
        if (!empty($filters['department'])) {
            $where[] = 'e.department_id = ?';
            $params[] = (int) $filters['department'];
        }
        if (in_array($filters['category'] ?? '', ['teaching', 'administrative', 'support'], true)) {
            $where[] = 'e.staff_category = ?';
            $params[] = $filters['category'];
        }
        $status = $filters['status'] ?? 'working';
        if ($status === 'working') {
            $where[] = "e.status <> 'dismissed'";
        } elseif (in_array($status, ['active', 'dismissed'], true)) {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(CONCAT_WS(" ", u.last_name, u.first_name, u.middle_name) LIKE ? OR e.personnel_number LIKE ? OR e.position LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return [$where, $params];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE e.id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findByUser(int $userId): ?array
    {
        return Database::fetch('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE e.user_id = ?', [$userId]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        Database::execute('INSERT INTO employees (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')', array_values($data));

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = ['personnel_number', 'iin', 'birth_date', 'gender', 'address', 'education', 'department_id', 'position', 'staff_category', 'employment_type', 'rate',
            'hire_date', 'dismissal_date', 'prior_experience_months', 'annual_leave_days', 'qualification_category', 'category_assigned_on', 'category_valid_until', 'status', 'note'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        Database::execute('UPDATE employees SET ' . implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data))) . ' WHERE id = ?', [...array_values($data), $id]);
    }

    public function nextPersonnelNumber(): string
    {
        $max = (int) Database::fetchColumn("SELECT MAX(CAST(personnel_number AS UNSIGNED)) FROM employees WHERE personnel_number REGEXP '^[0-9]+$'");

        return str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** @return list<array<string, mixed>> users that work here but have no personnel file (teachers and staff roles) */
    public function usersWithoutFile(): array
    {
        return Database::fetchAll(
            "SELECT DISTINCT u.id, u.last_name, u.first_name, u.middle_name, u.login FROM users u
             LEFT JOIN employees e ON e.user_id = u.id
             LEFT JOIN teachers t ON t.user_id = u.id
             LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
             WHERE e.id IS NULL AND u.deleted_at IS NULL
               AND (t.id IS NOT NULL OR r.slug IN ('admin','deputy_director','department_head','academic_office','teacher','curator','hr'))
               AND NOT EXISTS (SELECT 1 FROM students s WHERE s.user_id = u.id)
             ORDER BY u.last_name, u.first_name"
        );
    }

    /** @return list<array<string, mixed>> teachers without a personnel file (for the one-time registration) */
    public function teachersWithoutFile(): array
    {
        return Database::fetchAll(
            'SELECT t.* FROM teachers t LEFT JOIN employees e ON e.user_id = t.user_id WHERE e.id IS NULL ORDER BY t.id'
        );
    }

    /** Copy job data to the teacher profile of the same user (timetable and journals read it). */
    public function syncTeacher(int $userId, array $employee): void
    {
        Database::execute(
            'UPDATE teachers SET department_id = ?, position = ?, hire_date = ?, status = ? WHERE user_id = ?',
            [$employee['department_id'], $employee['position'], $employee['hire_date'], $employee['status'], $userId]
        );
    }

    // ---- orders --------------------------------------------------------------

    /**
     * @param array{type?: string, status?: string, year?: ?int} $filters
     * @return list<array<string, mixed>>
     */
    public function orders(array $filters, ?array $departments, ?int $employeeId = null): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($employeeId !== null) {
            $where[] = 'o.employee_id = ?';
            $params[] = $employeeId;
        }
        if ($departments !== null) {
            $where[] = $departments === [] ? '1 = 0' : 'e.department_id IN (' . implode(',', array_fill(0, count($departments), '?')) . ')';
            $params = array_merge($params, $departments);
        }
        foreach (['type' => 'o.type', 'status' => 'o.status'] as $key => $col) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "{$col} = ?";
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['year'])) {
            $where[] = 'YEAR(o.order_date) = ?';
            $params[] = (int) $filters['year'];
        }

        return Database::fetchAll(
            'SELECT o.*, u.last_name, u.first_name, u.middle_name, e.personnel_number FROM hr_orders o JOIN employees e ON e.id = o.employee_id JOIN users u ON u.id = e.user_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY o.order_date DESC, o.id DESC LIMIT 500',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function order(int $id): ?array
    {
        return Database::fetch('SELECT o.*, e.user_id, e.department_id AS employee_department_id FROM hr_orders o JOIN employees e ON e.id = o.employee_id WHERE o.id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function createOrder(array $data): int
    {
        Database::execute(
            'INSERT INTO hr_orders (employee_id, type, order_date, effective_date, details, basis, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['employee_id'], $data['type'], $data['order_date'], $data['effective_date'], $data['details'], $data['basis'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    public function setOrderStatus(int $id, string $status, ?string $number, ?int $userId): void
    {
        Database::execute(
            'UPDATE hr_orders SET status = ?, number = COALESCE(?, number), signed_at = ' . ($status === 'signed' ? 'NOW()' : 'signed_at') . ', signed_by = ' . ($status === 'signed' ? '?' : 'signed_by') . ' WHERE id = ?',
            $status === 'signed' ? [$status, $number, $userId, $id] : [$status, $number, $id]
        );
    }

    public function nextOrderNumber(string $prefix): string
    {
        $last = Database::fetchColumn('SELECT MAX(number) FROM hr_orders WHERE number LIKE ? FOR UPDATE', [$prefix . '%']);

        return $prefix . str_pad((string) (($last !== null ? (int) substr((string) $last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    // ---- leaves --------------------------------------------------------------

    /**
     * @param array{status?: string, type?: string, from?: string, to?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function leaves(array $filters, ?array $departments, ?int $employeeId = null): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($employeeId !== null) {
            $where[] = 'l.employee_id = ?';
            $params[] = $employeeId;
        }
        if ($departments !== null) {
            $where[] = $departments === [] ? '1 = 0' : 'e.department_id IN (' . implode(',', array_fill(0, count($departments), '?')) . ')';
            $params = array_merge($params, $departments);
        }
        foreach (['status' => 'l.status', 'type' => 'l.type'] as $key => $col) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = "{$col} = ?";
                $params[] = $filters[$key];
            }
        }
        if (($filters['from'] ?? '') !== '' && ($filters['to'] ?? '') !== '') {
            $where[] = 'l.start_date <= ? AND l.end_date >= ?';
            array_push($params, $filters['to'], $filters['from']);
        }

        return Database::fetchAll(
            'SELECT l.*, u.last_name, u.first_name, u.middle_name, e.position, e.department_id, e.user_id, t.id AS teacher_id
             FROM hr_leaves l JOIN employees e ON e.id = l.employee_id JOIN users u ON u.id = e.user_id LEFT JOIN teachers t ON t.user_id = e.user_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY l.start_date DESC, l.id DESC LIMIT 500',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function leave(int $id): ?array
    {
        return Database::fetch('SELECT l.*, e.user_id, e.department_id FROM hr_leaves l JOIN employees e ON e.id = l.employee_id WHERE l.id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function createLeave(array $data): int
    {
        Database::execute(
            'INSERT INTO hr_leaves (employee_id, type, start_date, end_date, days, status, reason, document_number, destination, requested_by, decided_by, decided_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['employee_id'], $data['type'], $data['start_date'], $data['end_date'], $data['days'], $data['status'], $data['reason'], $data['document_number'],
                $data['destination'], $data['requested_by'], $data['decided_by'], $data['decided_at']]
        );

        return Database::lastInsertId();
    }

    public function decideLeave(int $id, string $status, ?string $comment, ?int $userId): void
    {
        Database::execute('UPDATE hr_leaves SET status = ?, decision_comment = ?, decided_by = ?, decided_at = NOW() WHERE id = ?', [$status, $comment, $userId, $id]);
    }

    public function overlaps(int $employeeId, string $from, string $to, ?int $exceptId = null): bool
    {
        return Database::fetchColumn(
            "SELECT 1 FROM hr_leaves WHERE employee_id = ? AND status IN ('requested','approved') AND start_date <= ? AND end_date >= ?" . ($exceptId !== null ? ' AND id <> ?' : '') . ' LIMIT 1',
            array_merge([$employeeId, $to, $from], $exceptId !== null ? [$exceptId] : [])
        ) !== null;
    }

    /** Annual-leave days of the calendar year (approved, and optionally requested), counted inside the year. */
    public function annualDays(int $employeeId, int $year, bool $withRequested): int
    {
        return (int) Database::fetchColumn(
            "SELECT COALESCE(SUM(DATEDIFF(LEAST(end_date, ?), GREATEST(start_date, ?)) + 1), 0) FROM hr_leaves
             WHERE employee_id = ? AND type = 'annual' AND status IN (" . ($withRequested ? "'approved','requested'" : "'approved'") . ') AND start_date <= ? AND end_date >= ?',
            [$year . '-12-31', $year . '-01-01', $employeeId, $year . '-12-31', $year . '-01-01']
        );
    }

    // ---- trainings -----------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function trainings(int $employeeId): array
    {
        return Database::fetchAll('SELECT * FROM hr_trainings WHERE employee_id = ? ORDER BY end_date DESC', [$employeeId]);
    }

    /** @param array<string, mixed> $data */
    public function addTraining(array $data): int
    {
        Database::execute(
            'INSERT INTO hr_trainings (employee_id, title, provider, hours, start_date, end_date, certificate_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['employee_id'], $data['title'], $data['provider'], $data['hours'], $data['start_date'], $data['end_date'], $data['certificate_number'], $data['created_by']]
        );

        return Database::lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function training(int $id): ?array
    {
        return Database::fetch('SELECT * FROM hr_trainings WHERE id = ?', [$id]);
    }

    public function deleteTraining(int $id): void
    {
        Database::execute('DELETE FROM hr_trainings WHERE id = ?', [$id]);
    }

    // ---- reminders -----------------------------------------------------------

    /** @return list<array<string, mixed>> active teaching staff whose category expires before $until (or has expired) */
    public function expiringCategories(string $until, ?array $departments): array
    {
        [$scope, $params] = $this->scope($departments);

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . " WHERE e.status <> 'dismissed' AND e.category_valid_until IS NOT NULL AND e.category_valid_until <= ? AND {$scope}
             ORDER BY e.category_valid_until",
            array_merge([$until], $params)
        );
    }

    /** @return list<array<string, mixed>> active teaching staff without professional development since $since */
    public function trainingDue(string $since, ?array $departments): array
    {
        [$scope, $params] = $this->scope($departments);

        return Database::fetchAll(
            'SELECT ' . self::COLUMNS . self::JOINS . " WHERE e.status <> 'dismissed' AND e.staff_category = 'teaching' AND {$scope}
             AND NOT EXISTS (SELECT 1 FROM hr_trainings tr WHERE tr.employee_id = e.id AND tr.end_date >= ?) ORDER BY u.last_name",
            array_merge($params, [$since])
        );
    }

    /** @return array{0: string, 1: list<int>} */
    private function scope(?array $departments): array
    {
        if ($departments === null) {
            return ['1 = 1', []];
        }

        return $departments === [] ? ['1 = 0', []] : ['e.department_id IN (' . implode(',', array_fill(0, count($departments), '?')) . ')', $departments];
    }

    /** Dismissed employees whose date has come but whose account is still active. @return list<array<string, mixed>> */
    public function dueDismissals(string $today): array
    {
        return Database::fetchAll(
            "SELECT e.id, e.user_id FROM employees e JOIN users u ON u.id = e.user_id WHERE e.status = 'dismissed' AND e.dismissal_date <= ? AND u.is_active = 1",
            [$today]
        );
    }
}
