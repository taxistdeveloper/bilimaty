<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Application;
use App\Core\Config;
use App\Core\Tenant;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\PasswordGenerator;
use PDO;
use Throwable;

/**
 * Owner's operations: colleges (create with their own database, edit, suspend), the first
 * administrator of a college, invoices and payments (a paid invoice extends paid_until),
 * usage statistics of each college and the owner's audit trail.
 */
final class PlatformService
{
    public const METHODS = ['bank', 'kaspi', 'cash', 'other'];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null, private readonly ?int $adminId = null)
    {
        $this->pdo = $pdo ?? Platform::pdo();
    }

    // ---- colleges ------------------------------------------------------------

    /** @return array<string, string> */
    public static function collegeRules(?array $college = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:100',
            'bin' => 'nullable|string|max:12',
            'contact_name' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|phone|max:20',
            'contact_email' => 'nullable|email|max:190',
            'plan_id' => 'nullable|integer|exists:plans,id',
            'monthly_price' => 'nullable|decimal:2|min:0|max:100000000',
            'grace_days' => 'required|integer|between:0,60',
            'note' => 'nullable|string|max:500',
        ];
        if ($college === null) {
            $rules += [
                'code' => 'required|string|max:30',
                'admin_login' => 'nullable|alpha_dash|min:3|max:64',
                'existing_db' => 'nullable|alpha_dash|max:64',
            ];
        } else {
            $rules['paid_until'] = 'nullable|date';
        }

        return $rules;
    }

    /**
     * Create a college: its database (or an existing one), migrations, base data and the first
     * administrator with a temporary password (shown once).
     *
     * @param array<string, mixed> $data validated (collegeRules)
     * @return array{id: int, login: ?string, password: ?string}
     */
    public function createCollege(array $data, ?callable $out = null): array
    {
        $data = $this->withPlanPrice($data);
        $out ??= static fn (string $line) => null;
        $code = strtolower(trim((string) $data['code']));
        $errors = [];
        if (!Tenant::isValidCode($code)) {
            $errors['code'][] = __('platform.code_invalid');
        } elseif ($this->value('SELECT 1 FROM colleges WHERE code = ?', [$code]) !== null) {
            $errors['code'][] = __('platform.code_taken');
        }
        $existing = trim((string) ($data['existing_db'] ?? ''));
        $db = $existing !== '' ? $existing : Tenant::databaseFor($code);
        if ($errors === [] && $this->value('SELECT 1 FROM colleges WHERE db_name = ?', [$db]) !== null) {
            $errors['existing_db'][] = __('platform.db_taken');
        }
        if ($errors === [] && $existing !== '' && !Platform::databaseExists($existing)) {
            $errors['existing_db'][] = __('platform.db_missing');
        }
        if ($errors === [] && $existing === '' && Platform::databaseExists($db)) {
            $errors['code'][] = __('platform.db_exists', ['db' => $db]);
        }
        if ($db === Platform::database()) {
            $errors['existing_db'][] = __('platform.db_taken');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        Platform::createDatabase($db);
        $tenant = Platform::tenantPdo($db);
        Platform::tenantMigrator($tenant)->migrate($out);
        Platform::seedTenant($tenant, $out);

        $login = null;
        $password = null;
        if ($existing === '') {
            $login = trim((string) ($data['admin_login'] ?? '')) ?: 'admin';
            $password = $this->createTenantAdmin($tenant, $login, (string) $data['name']);
        } elseif (trim((string) ($data['admin_login'] ?? '')) !== '') {
            $login = trim((string) $data['admin_login']);
        }
        $this->setTenantCollegeName($tenant, (string) $data['name'], $existing === '' ? null : false);

        $this->run(
            'INSERT INTO colleges (code, name, db_name, city, bin, contact_name, contact_phone, contact_email, admin_login, plan_id, monthly_price, paid_until, grace_days, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $data['name'], $db, $data['city'] ?? null, $data['bin'] ?? null, $data['contact_name'] ?? null, $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null, $login, $data['plan_id'] ?? null, $data['monthly_price'], date('Y-m-d', strtotime('+' . (int) Config::get('tenancy.trial_days', 14) . ' days')),
                (int) $data['grace_days'], $data['note'] ?? null]
        );
        $id = (int) $this->pdo->lastInsertId();
        $this->audit('college.created', $id, ['code' => $code, 'db' => $db, 'existing' => $existing !== '']);

        return ['id' => $id, 'login' => $login, 'password' => $password];
    }

    /** @param array<string, mixed> $data validated (collegeRules with a college) */
    public function updateCollege(array $college, array $data): void
    {
        $data = $this->withPlanPrice($data);
        $fields = ['name', 'city', 'bin', 'contact_name', 'contact_phone', 'contact_email', 'plan_id', 'monthly_price', 'grace_days', 'note', 'paid_until'];
        $new = array_intersect_key($data, array_flip($fields));
        $this->run('UPDATE colleges SET ' . implode(', ', array_map(static fn (string $f): string => "`{$f}` = ?", array_keys($new))) . ' WHERE id = ?', [...array_values($new), $college['id']]);
        $changed = array_keys(array_diff_assoc(array_map('strval', $new), array_map('strval', array_intersect_key($college, $new))));
        if ($changed !== []) {
            $keys = array_flip($changed);
            $this->audit('college.updated', (int) $college['id'], ['old' => array_intersect_key($college, $keys), 'new' => array_intersect_key($new, $keys)]);
        }
        if (($new['name'] ?? $college['name']) !== $college['name']) {
            $this->setTenantCollegeName(Platform::tenantPdo((string) $college['db_name']), (string) $new['name'], (string) $college['name']);
        }
    }

    /** An empty price takes the price of the chosen tariff (or 0). */
    private function withPlanPrice(array $data): array
    {
        $data['plan_id'] = !empty($data['plan_id']) ? (int) $data['plan_id'] : null;
        if (($data['monthly_price'] ?? null) === null || $data['monthly_price'] === '') {
            $data['monthly_price'] = $data['plan_id'] !== null ? (string) $this->value('SELECT monthly_price FROM plans WHERE id = ?', [$data['plan_id']]) : '0';
        }

        return $data;
    }

    public function setStatus(array $college, string $status): void
    {
        $this->run('UPDATE colleges SET status = ? WHERE id = ?', [$status, $college['id']]);
        $this->audit($status === 'suspended' ? 'college.suspended' : 'college.resumed', (int) $college['id'], null);
    }

    /** New temporary password of the college's first administrator (re-activated if needed). */
    public function resetAdminPassword(array $college): array
    {
        $login = (string) ($college['admin_login'] ?? '');
        $tenant = Platform::tenantPdo((string) $college['db_name']);
        $st = $tenant->prepare('SELECT id FROM users WHERE login = ? AND deleted_at IS NULL');
        $st->execute([$login]);
        $userId = $st->fetchColumn();
        if ($login === '' || $userId === false) {
            throw new ValidationException(['admin_login' => [__('platform.admin_missing')]]);
        }
        $password = PasswordGenerator::generate(14);
        $tenant->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NOW(), is_active = 1 WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $tenant->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([mb_strtolower($login)]);
        $this->audit('college.admin_password', (int) $college['id'], ['login' => $login]);

        return ['login' => $login, 'password' => $password];
    }

    private function createTenantAdmin(PDO $tenant, string $login, string $collegeName): string
    {
        $st = $tenant->prepare('SELECT id FROM users WHERE login = ?');
        $st->execute([$login]);
        if ($st->fetchColumn() !== false) {
            throw new ValidationException(['admin_login' => [__('platform.admin_exists')]]);
        }
        $password = PasswordGenerator::generate(14);
        $tenant->prepare(
            "INSERT INTO users (login, password_hash, last_name, first_name, locale, is_active, password_changed_at, must_change_password)
             VALUES (?, ?, 'Администратор', ?, 'ru', 1, NOW(), 1)"
        )->execute([$login, password_hash($password, PASSWORD_DEFAULT), mb_substr($collegeName, 0, 100)]);
        $userId = (int) $tenant->lastInsertId();
        $tenant->prepare("INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = 'super_admin'")->execute([$userId]);

        return $password;
    }

    /**
     * Name of the college in its own settings (documents, sign-in page).
     *
     * @param string|false|null $previous null — new database: always set; false — existing database: only when
     *                                    empty; a string — set when the college still has that old name
     */
    private function setTenantCollegeName(PDO $tenant, string $name, string|false|null $previous): void
    {
        $st = $tenant->prepare("SELECT `value` FROM settings WHERE `key` = 'college_name_ru'");
        $st->execute();
        $current = $st->fetchColumn();
        $empty = $current === false || $current === null || $current === '';
        if ($previous === null || $empty || (is_string($previous) && $current === $previous)) {
            $tenant->prepare("INSERT INTO settings (`key`, `value`, `type`) VALUES ('college_name_ru', ?, 'string') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$name]);
        }
    }

    // ---- invoices ------------------------------------------------------------

    /** @param array<string, mixed> $input months, amount, issued_on, note */
    public function createInvoice(array $college, array $input): int
    {
        $v = Validator::make($input, [
            'months' => 'required|integer|between:1,36',
            'amount' => 'nullable|decimal:2|min:0|max:1000000000',
            'issued_on' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            throw new ValidationException($v->errors(), $input);
        }
        $d = $v->validated();
        $amount = $d['amount'] ?? null;
        $amount = $amount !== null ? (string) $amount : number_format((float) $college['monthly_price'] * (int) $d['months'], 2, '.', '');
        $this->pdo->beginTransaction();
        try {
            $prefix = Config::get('tenancy.invoice_prefix', 'СЧ') . '-' . substr((string) $d['issued_on'], 0, 4) . '-';
            $st = $this->pdo->prepare('SELECT MAX(number) FROM college_invoices WHERE number LIKE ? FOR UPDATE');
            $st->execute([$prefix . '%']);
            $last = $st->fetchColumn();
            $number = $prefix . str_pad((string) (($last ? (int) substr((string) $last, strlen($prefix)) : 0) + 1), 5, '0', STR_PAD_LEFT);
            $this->run('INSERT INTO college_invoices (college_id, number, issued_on, months, amount, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$college['id'], $number, $d['issued_on'], $d['months'], $amount, $d['note'] ?? null, $this->adminId]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->audit('invoice.created', (int) $college['id'], ['number' => $number, 'amount' => $amount, 'months' => $d['months']]);

        return $id;
    }

    /**
     * Mark an invoice paid: the paid months continue the current term (or start on the payment
     * date when the term has already ended) and paid_until moves to the end of the new period.
     *
     * @return array{from: string, to: string}
     */
    public function payInvoice(array $invoice, array $input): array
    {
        if ($invoice['status'] !== 'issued') {
            throw new ValidationException(['status' => [__('platform.invoice_not_issued')]]);
        }
        $v = Validator::make($input, ['paid_on' => 'required|date', 'method' => 'required|in:' . implode(',', self::METHODS)]);
        if ($v->fails()) {
            throw new ValidationException($v->errors(), $input);
        }
        $d = $v->validated();
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('SELECT * FROM colleges WHERE id = ? FOR UPDATE');
            $st->execute([$invoice['college_id']]);
            $college = $st->fetch();
            [$from, $to] = self::period($college['paid_until'] ?? null, (string) $d['paid_on'], (int) $invoice['months']);
            $this->run("UPDATE college_invoices SET status = 'paid', paid_on = ?, method = ?, period_from = ?, period_to = ? WHERE id = ?",
                [$d['paid_on'], $d['method'], $from, $to, $invoice['id']]);
            $this->run('UPDATE colleges SET paid_until = ? WHERE id = ?', [$to, $invoice['college_id']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        $this->audit('invoice.paid', (int) $invoice['college_id'], ['number' => $invoice['number'], 'from' => $from, 'to' => $to, 'old_paid_until' => $college['paid_until'] ?? null]);

        return ['from' => $from, 'to' => $to];
    }

    /** @return array{0: string, 1: string} paid period: continues the term or starts on the payment date */
    public static function period(?string $paidUntil, string $paidOn, int $months): array
    {
        $from = $paidUntil !== null && $paidUntil !== '' && $paidUntil >= $paidOn
            ? date('Y-m-d', strtotime($paidUntil . ' +1 day'))
            : $paidOn;
        $to = (new \DateTimeImmutable($from))->modify('+' . $months . ' months')->modify('-1 day')->format('Y-m-d');

        return [$from, $to];
    }

    public function cancelInvoice(array $invoice): void
    {
        if ($invoice['status'] !== 'issued') {
            throw new ValidationException(['status' => [__('platform.invoice_not_issued')]]);
        }
        $this->run("UPDATE college_invoices SET status = 'cancelled' WHERE id = ?", [$invoice['id']]);
        $this->audit('invoice.cancelled', (int) $invoice['college_id'], ['number' => $invoice['number']]);
    }

    // ---- statistics ----------------------------------------------------------

    /**
     * Usage of a college (live from its database). Never throws: a missing database gives 'error'.
     *
     * @return array<string, mixed>
     */
    public static function stats(array $college): array
    {
        try {
            $t = Platform::tenantPdo((string) $college['db_name']);
            $q = static function (string $sql, array $p = []) use ($t): mixed {
                $st = $t->prepare($sql);
                $st->execute($p);

                return $st->fetchColumn();
            };
            $since = date('Y-m-d H:i:s', strtotime('-30 days'));

            return [
                'students' => (int) $q("SELECT COUNT(*) FROM students WHERE status = 'active'"),
                'teachers' => (int) $q("SELECT COUNT(*) FROM teachers WHERE status <> 'dismissed'"),
                'groups' => (int) $q("SELECT COUNT(*) FROM `groups` WHERE status = 'active'"),
                'users' => (int) $q('SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL'),
                'active_users_30' => (int) $q('SELECT COUNT(*) FROM users WHERE last_login_at >= ?', [$since]),
                'logins_30' => (int) $q('SELECT COUNT(*) FROM login_attempts WHERE successful = 1 AND attempted_at >= ?', [$since]),
                'grades_30' => (int) $q('SELECT COUNT(*) FROM journal_grades WHERE updated_at >= ?', [$since]),
                'documents_30' => (int) $q('SELECT COUNT(*) FROM document_requests WHERE created_at >= ?', [$since]),
                'last_login' => $q('SELECT MAX(last_login_at) FROM users') ?: null,
                'size_mb' => round((float) $q('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = ?', [$college['db_name']]) / 1048576, 1),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ---- helpers -------------------------------------------------------------

    public function audit(string $action, ?int $collegeId, ?array $details): void
    {
        $ip = null;
        try {
            $ip = Application::instance()->request()?->ip();
        } catch (Throwable) {
        }
        $this->run('INSERT INTO platform_audit (admin_id, action, college_id, details, ip_address) VALUES (?, ?, ?, ?, ?)',
            [$this->adminId, $action, $collegeId, $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, $ip]);
    }

    private function run(string $sql, array $params): void
    {
        $this->pdo->prepare($sql)->execute($params);
    }

    private function value(string $sql, array $params): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();

        return $v === false ? null : $v;
    }
}
