<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Tenant;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Services\LoginGenerator;
use App\Services\PasswordGenerator;
use PDO;
use RuntimeException;

/**
 * The owner's work inside a college: one-time sign-in as its administrator, its users,
 * tariff limits, announcements, backups, archive and deletion of the database.
 * Every action is written to the platform audit and, when it touches the college, to the
 * college's own audit log as well.
 */
final class CollegeManager
{
    public const ADMIN_ROLES = ['super_admin', 'admin'];
    private const TOKEN_TTL = 60; // seconds

    private PlatformService $platform;

    public function __construct(private readonly ?int $adminId = null, private readonly string $adminName = '')
    {
        $this->platform = new PlatformService(null, $adminId);
    }

    // ---- sign-in to a college ------------------------------------------------

    /** One-time link for the owner to enter the college as one of its administrators. */
    public function loginLink(array $college, int $userId): string
    {
        $this->assertDatabase($college);
        $user = $this->user($college, $userId);
        if ($user === null || (int) $user['is_active'] !== 1 || array_intersect(self::ADMIN_ROLES, explode(',', (string) $user['roles'])) === []) {
            throw new ValidationException(['user_id' => [__('platform.impersonate_admins_only')]]);
        }
        $token = bin2hex(random_bytes(32));
        Platform::pdo()->prepare('INSERT INTO college_login_tokens (token_hash, college_id, user_id, admin_id, expires_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([hash('sha256', $token), $college['id'], $userId, $this->adminId, date('Y-m-d H:i:s', time() + self::TOKEN_TTL)]);
        $this->platform->audit('college.impersonate', (int) $college['id'], ['login' => $user['login']]);

        return '/' . $college['code'] . '/owner-login?token=' . $token;
    }

    /**
     * Inside the college: exchange a link for the user id (once, before it expires).
     *
     * @return array{user_id: int, admin: string}|null
     */
    public static function consume(array $college, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $pdo = Platform::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT t.*, a.name AS admin_name FROM college_login_tokens t JOIN platform_admins a ON a.id = t.admin_id
                                 WHERE t.token_hash = ? AND t.college_id = ? AND t.used_at IS NULL AND t.expires_at >= NOW() AND a.is_active = 1 FOR UPDATE');
            $st->execute([hash('sha256', $token), $college['id']]);
            $row = $st->fetch();
            if ($row === false) {
                $pdo->rollBack();

                return null;
            }
            $pdo->prepare('UPDATE college_login_tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['user_id' => (int) $row['user_id'], 'admin' => (string) $row['admin_name']];
    }

    // ---- users of a college ---------------------------------------------------

    /**
     * @param array{q?: string, role?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function users(array $college, array $filters, int $page): array
    {
        $pdo = $this->tenant($college);
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(u.login LIKE ? OR CONCAT_WS(" ", u.last_name, u.first_name, u.middle_name) LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (($filters['role'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles x JOIN roles y ON y.id = x.role_id WHERE x.user_id = u.id AND y.slug = ?)';
            $params[] = $filters['role'];
        }
        $sql = ' FROM users u WHERE ' . implode(' AND ', $where);
        $st = $pdo->prepare('SELECT COUNT(*)' . $sql);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $pages = max(1, (int) ceil($total / 30));
        $page = min(max(1, $page), $pages);
        $st = $pdo->prepare('SELECT u.id, u.login, u.email, u.last_name, u.first_name, u.middle_name, u.is_active, u.last_login_at,
            (SELECT GROUP_CONCAT(r.slug ORDER BY r.id) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles'
            . $sql . ' ORDER BY (SELECT MIN(r.id) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) IS NULL, u.last_name, u.first_name LIMIT 30 OFFSET ' . (($page - 1) * 30));
        $st->execute($params);

        return ['items' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array{slug: string, name: string}> roles of the college */
    public function roles(array $college): array
    {
        $col = locale() === 'kk' ? 'name_kk' : 'name_ru';

        return $this->tenant($college)->query("SELECT slug, {$col} AS name FROM roles ORDER BY id")->fetchAll();
    }

    public function setActive(array $college, int $userId, bool $active): void
    {
        $user = $this->user($college, $userId) ?? throw new ValidationException(['user_id' => [__('platform.user_missing')]]);
        $this->tenant($college)->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $userId]);
        $this->log($college, $active ? 'platform.user_unblocked' : 'platform.user_blocked', $userId, ['login' => $user['login']]);
    }

    /** @return array{login: string, password: string} */
    public function resetPassword(array $college, int $userId): array
    {
        $user = $this->user($college, $userId) ?? throw new ValidationException(['user_id' => [__('platform.user_missing')]]);
        $password = PasswordGenerator::generate(14);
        $pdo = $this->tenant($college);
        $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NOW() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([mb_strtolower((string) $user['login'])]);
        $this->log($college, 'platform.user_password', $userId, ['login' => $user['login']]);

        return ['login' => (string) $user['login'], 'password' => $password];
    }

    /**
     * Another administrator of the college (role super_admin or admin).
     *
     * @return array{login: string, password: string}
     */
    public function addAdmin(array $college, array $input): array
    {
        $pdo = $this->tenant($college);
        $v = Validator::make($input, [
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'login' => 'nullable|alpha_dash|min:3|max:64',
            'email' => 'nullable|email|max:190',
            'role' => 'required|in:' . implode(',', self::ADMIN_ROLES),
        ]);
        $errors = $v->errors();
        $d = $v->validated();
        $login = trim((string) ($d['login'] ?? '')) ?: LoginGenerator::generate((string) ($d['last_name'] ?? 'admin'), (string) ($d['first_name'] ?? ''), null);
        foreach (['login' => $login, 'email' => $d['email'] ?? null] as $field => $value) {
            if ($value !== null && $value !== '' && !isset($errors[$field])) {
                $st = $pdo->prepare("SELECT 1 FROM users WHERE {$field} = ?");
                $st->execute([$value]);
                if ($st->fetchColumn() !== false) {
                    $errors[$field][] = __('validation.unique', ['field' => __('fields.' . $field)]);
                }
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }
        $password = PasswordGenerator::generate(14);
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO users (login, email, password_hash, last_name, first_name, locale, is_active, password_changed_at, must_change_password)
                           VALUES (?, ?, ?, ?, ?, 'ru', 1, NOW(), 1)")
                ->execute([$login, $d['email'] ?? null, password_hash($password, PASSWORD_DEFAULT), $d['last_name'], $d['first_name']]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?')->execute([$userId, $d['role']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->log($college, 'platform.admin_added', $userId, ['login' => $login, 'role' => $d['role']]);

        return ['login' => $login, 'password' => $password];
    }

    // ---- plans ---------------------------------------------------------------

    /** @return array<string, string> */
    public static function planRules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'max_students' => 'nullable|integer|min:1|max:1000000',
            'monthly_price' => 'required|decimal:2|min:0|max:100000000',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function savePlan(?array $plan, array $data): int
    {
        $pdo = Platform::pdo();
        $st = $pdo->prepare('SELECT id FROM plans WHERE name = ?' . ($plan !== null ? ' AND id <> ?' : ''));
        $st->execute($plan !== null ? [$data['name'], $plan['id']] : [$data['name']]);
        if ($st->fetchColumn() !== false) {
            throw new ValidationException(['name' => [__('validation.unique', ['field' => __('fields.name')])]], $data);
        }
        $values = [$data['name'], $data['max_students'] ?? null, $data['monthly_price'], $data['description'] ?? null, !empty($data['is_active']) ? 1 : 0];
        if ($plan === null) {
            $pdo->prepare('INSERT INTO plans (name, max_students, monthly_price, description, is_active) VALUES (?, ?, ?, ?, ?)')->execute($values);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE plans SET name = ?, max_students = ?, monthly_price = ?, description = ?, is_active = ? WHERE id = ?')->execute([...$values, $plan['id']]);
            $id = (int) $plan['id'];
        }
        $this->platform->audit($plan === null ? 'plan.created' : 'plan.updated', null, ['plan' => $data['name'], 'max_students' => $data['max_students'] ?? null]);

        return $id;
    }

    // ---- announcements -------------------------------------------------------

    public function addAnnouncement(array $input): int
    {
        $v = Validator::make($input, [
            'college_id' => 'nullable|integer',
            'audience' => 'required|in:all,admins',
            'level' => 'required|in:info,warning',
            'title' => 'required|string|max:200',
            'body' => 'nullable|string|max:2000',
            'starts_on' => 'required|date',
            'ends_on' => 'nullable|date',
        ]);
        $errors = $v->errors();
        $d = $v->validated();
        if (!isset($errors['ends_on']) && ($d['ends_on'] ?? null) !== null && $d['ends_on'] < ($d['starts_on'] ?? '')) {
            $errors['ends_on'][] = __('validation.after', ['field' => __('fields.ends_on'), 'other' => __('fields.starts_on')]);
        }
        $collegeId = !empty($d['college_id']) ? (int) $d['college_id'] : null;
        if ($collegeId !== null) {
            $st = Platform::pdo()->prepare('SELECT 1 FROM colleges WHERE id = ?');
            $st->execute([$collegeId]);
            if ($st->fetchColumn() === false) {
                $errors['college_id'][] = __('platform.college_missing');
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }
        Platform::pdo()->prepare('INSERT INTO announcements (college_id, audience, level, title, body, starts_on, ends_on, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$collegeId, $d['audience'], $d['level'], $d['title'], $d['body'] ?? null, $d['starts_on'], $d['ends_on'] ?? null, $this->adminId]);
        $id = (int) Platform::pdo()->lastInsertId();
        $this->platform->audit('announcement.created', $collegeId, ['title' => $d['title']]);

        return $id;
    }

    public function deleteAnnouncement(array $announcement): void
    {
        Platform::pdo()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$announcement['id']]);
        $this->platform->audit('announcement.deleted', $announcement['college_id'] !== null ? (int) $announcement['college_id'] : null, ['title' => $announcement['title']]);
    }

    /** Announcements shown now in the current college. @return list<array<string, mixed>> */
    public static function current(array $college, bool $isAdmin): array
    {
        try {
            $st = Platform::pdo()->prepare(
                "SELECT id, level, title, body FROM announcements
                 WHERE (college_id IS NULL OR college_id = ?) AND starts_on <= CURDATE() AND (ends_on IS NULL OR ends_on >= CURDATE())
                   AND (audience = 'all' OR ?) ORDER BY level = 'warning' DESC, starts_on DESC LIMIT 5"
            );
            $st->execute([$college['id'], $isAdmin ? 1 : 0]);

            return $st->fetchAll();
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::report($e);

            return [];
        }
    }

    // ---- backups, archive ----------------------------------------------------

    public static function backupDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/private/backups';
    }

    /** @return list<array{name: string, size: int, time: int}> backups of the college, newest first */
    public static function backups(array $college): array
    {
        $out = [];
        foreach (glob(self::backupDir() . '/' . $college['code'] . '-*.sql.gz') ?: [] as $file) {
            $out[] = ['name' => basename($file), 'size' => (int) filesize($file), 'time' => (int) filemtime($file)];
        }
        usort($out, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        return $out;
    }

    /** Full copy of the college database: structure and data as SQL, gzip. */
    public function backup(array $college): string
    {
        $this->assertDatabase($college);
        $dir = self::backupDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create ' . $dir);
        }
        $name = $college['code'] . '-' . date('Ymd-His') . '.sql.gz';
        $path = $dir . '/' . $name;
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Cannot write ' . $path);
        }
        $pdo = $this->tenant($college);
        gzwrite($gz, "-- BILIMATY backup of {$college['code']} ({$college['db_name']}) " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n");
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '`', PDO::FETCH_NUM);
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = '(' . implode(',', array_map(static fn ($v): string => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                if (count($batch) === 200) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                gzwrite($gz, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        gzclose($gz);
        $this->platform->audit('college.backup', (int) $college['id'], ['file' => $name, 'size' => filesize($path)]);

        return $name;
    }

    /** Absolute path of a backup of this college (null for anything else). */
    public static function backupPath(array $college, string $name): ?string
    {
        if (!preg_match('/^' . preg_quote((string) $college['code'], '/') . '-\d{8}-\d{6}\.sql\.gz$/', $name)) {
            return null;
        }
        $path = self::backupDir() . '/' . $name;

        return is_file($path) ? $path : null;
    }

    public function archive(array $college, bool $archive): void
    {
        if (!$archive && $college['db_deleted_at'] !== null) {
            throw new ValidationException(['status' => [__('platform.db_deleted')]]);
        }
        Platform::pdo()->prepare('UPDATE colleges SET status = ?, archived_at = ? WHERE id = ?')
            ->execute([$archive ? 'archived' : 'active', $archive ? date('Y-m-d H:i:s') : null, $college['id']]);
        $this->platform->audit($archive ? 'college.archived' : 'college.restored', (int) $college['id'], null);
    }

    /** Delete the database of an archived college: only after a backup and with its code typed in. */
    public function deleteDatabase(array $college, string $confirm): void
    {
        $errors = [];
        if ($college['status'] !== 'archived') {
            $errors['confirm'][] = __('platform.delete_archive_first');
        } elseif ($college['db_deleted_at'] !== null) {
            $errors['confirm'][] = __('platform.db_deleted');
        } elseif (trim($confirm) !== $college['code']) {
            $errors['confirm'][] = __('platform.delete_confirm_code');
        } elseif (self::backups($college) === [] || self::backups($college)[0]['time'] < strtotime((string) $college['archived_at'])) {
            $errors['confirm'][] = __('platform.delete_backup_first');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $server = \App\Core\Database::connect(Platform::config(null), false);
        $server->exec('DROP DATABASE IF EXISTS ' . \App\Core\Database::identifier((string) $college['db_name']));
        Platform::pdo()->prepare('UPDATE colleges SET db_deleted_at = NOW() WHERE id = ?')->execute([$college['id']]);
        $this->platform->audit('college.db_deleted', (int) $college['id'], ['db' => $college['db_name']]);
    }

    // ---- helpers -------------------------------------------------------------

    private function tenant(array $college): PDO
    {
        $this->assertDatabase($college);

        return Platform::tenantPdo((string) $college['db_name']);
    }

    private function assertDatabase(array $college): void
    {
        if (($college['db_deleted_at'] ?? null) !== null) {
            throw new ValidationException(['status' => [__('platform.db_deleted')]]);
        }
    }

    /** @return array<string, mixed>|null */
    private function user(array $college, int $userId): ?array
    {
        $st = $this->tenant($college)->prepare('SELECT u.*, (SELECT GROUP_CONCAT(r.slug) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles
                                                 FROM users u WHERE u.id = ? AND u.deleted_at IS NULL');
        $st->execute([$userId]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    }

    /** Both audit trails: the owner's and the college's own. */
    private function log(array $college, string $action, int $userId, array $details): void
    {
        $this->platform->audit(str_replace('platform.', 'college.', $action), (int) $college['id'], $details);
        try {
            $this->tenant($college)->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values) VALUES (NULL, ?, ?, ?, ?)')
                ->execute([$action, 'users', $userId, json_encode($details + ['owner' => $this->adminName], JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::report($e);
        }
    }
}
