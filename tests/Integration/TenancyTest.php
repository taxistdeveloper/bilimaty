<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;

/**
 * Several colleges on one installation (TENANCY=true), on its own server and databases:
 * owner's panel, creating colleges with their own databases, isolation of data and sessions,
 * subscription (grace → read-only, suspension), invoices that extend the term, reports.
 */
final class TenancyTest extends IntegrationTestCase
{
    private const PLATFORM = 'collegeos_test_platform';
    private const PREFIX = 'collegeos_test_t_';
    private const NEW_PASSWORD = 'Owner2026secure!';

    private static ?string $url = null;

    private function url(): string
    {
        if (self::$url !== null) {
            return self::$url;
        }
        $root = dirname(__DIR__, 2);
        $server = Environment::server();
        foreach ($server->query("SHOW DATABASES LIKE 'collegeos\\_test\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $db) {
            if (str_starts_with($db, self::PREFIX) || $db === self::PLATFORM) {
                $server->exec('DROP DATABASE `' . $db . '`');
            }
        }
        $env = ['TENANCY' => 'true', 'PLATFORM_DB_DATABASE' => self::PLATFORM, 'TENANT_DB_PREFIX' => self::PREFIX];
        foreach ($env as $k => $v) {
            putenv("{$k}={$v}");
        }
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/cli.php') . ' platform:install owner 2>&1', $out, $code);
        putenv('TENANCY=false'); // the CLI of other tests stays single-college
        $this->assertSame(0, $code, implode("\n", $out));
        $this->platform()->prepare('UPDATE platform_admins SET password_hash = ? WHERE login = ?')->execute([password_hash(Environment::PASSWORD, PASSWORD_DEFAULT), 'owner']);

        return self::$url = Environment::serve($root, $env);
    }

    private function platform(): PDO
    {
        $d = Environment::db();

        return new PDO('mysql:host=' . $d['host'] . ';port=' . $d['port'] . ';dbname=' . self::PLATFORM . ';charset=utf8mb4', $d['username'], $d['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    private function tenant(string $code): PDO
    {
        $d = Environment::db();

        return new PDO('mysql:host=' . $d['host'] . ';port=' . $d['port'] . ';dbname=' . self::PREFIX . str_replace('-', '_', $code) . ';charset=utf8mb4', $d['username'], $d['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    private function pvalue(string $sql, array $params = []): mixed
    {
        $st = $this->platform()->prepare($sql);
        $st->execute($params);

        return $st->fetchColumn();
    }

    /** Owner signed in with a changed password. */
    private function owner(): Http
    {
        $owner = (new Http($this->url(), '/platform'))->login('owner');
        if ((int) $this->pvalue("SELECT must_change_password FROM platform_admins WHERE login = 'owner'") === 1) {
            $owner->submit('/password', ['current_password' => Environment::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);
            $this->platform()->prepare('UPDATE platform_admins SET password_hash = ? WHERE login = ?')->execute([password_hash(Environment::PASSWORD, PASSWORD_DEFAULT), 'owner']);
        }

        return $owner;
    }

    /** Create a college through the panel; returns the temporary password of its administrator. */
    private function college(Http $owner, string $code, string $name): string
    {
        if ($this->pvalue('SELECT id FROM colleges WHERE code = ?', [$code]) !== false) {
            $this->tenant($code)->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE login = 'admin'")->execute([password_hash(Environment::PASSWORD, PASSWORD_DEFAULT)]);

            return Environment::PASSWORD;
        }
        $r = $owner->submit('/colleges', ['name' => $name, 'code' => $code, 'admin_login' => 'admin', 'monthly_price' => '50000', 'grace_days' => '7']);
        $this->assertTrue(preg_match('/id="tempPassword"[^>]*|value="([^"]+)" readonly id="tempPassword"/', $r['body'], $m) === 1 && ($m[1] ?? '') !== '', 'Temporary password shown once: ' . substr(strip_tags($r['body']), 0, 300));

        return html_entity_decode($m[1]);
    }

    private function signIn(string $code, string $password): Http
    {
        $http = (new Http($this->url(), '/' . $code))->login('admin', $password);
        if ($password !== Environment::PASSWORD) {
            $http->submit('/profile/password', ['_method' => 'PUT', 'current_password' => $password, 'password' => Environment::PASSWORD, 'password_confirmation' => Environment::PASSWORD]);
        }

        return $http;
    }

    public function testOwnerPanelAndCollegesWithOwnDatabases(): void
    {
        $anon = new Http($this->url());
        $this->assertSame(200, $anon->get('/')['status'], 'Code entry page');
        $this->assertSame(404, $anon->get('/?code=nonexistent')['status'], 'Unknown code');
        $this->assertSame(404, $anon->get('/nonexistent/login')['status']);
        $this->assertSame('/platform/login', $anon->get('/platform/')['location'], 'Panel needs sign-in');

        $owner = $this->owner();
        $this->assertSame(200, $owner->get('/')['status']);
        $alpha = $this->college($owner, 'alpha', 'Колледж Альфа');
        $beta = $this->college($owner, 'beta', 'Колледж Бета');
        $this->assertSame(1, (int) $this->tenant('alpha')->query("SELECT COUNT(*) FROM users WHERE login = 'admin'")->fetchColumn(), 'Own database with its administrator');
        $this->assertSame('Колледж Альфа', $this->tenant('alpha')->query("SELECT `value` FROM settings WHERE `key` = 'college_name_ru'")->fetchColumn());
        $this->assertTrue((int) $this->tenant('beta')->query('SELECT COUNT(*) FROM roles')->fetchColumn() > 5, 'Roles seeded');
        $this->assertSame('/alpha/login', $anon->get('/?code=ALPHA')['location']);
        $owner->submit('/colleges', ['name' => 'Дубль', 'code' => 'alpha', 'monthly_price' => '1', 'grace_days' => '7']);
        $this->assertSame(1, (int) $this->pvalue("SELECT COUNT(*) FROM colleges WHERE code = 'alpha'"), 'Codes are unique');
        $owner->submit('/colleges', ['name' => 'Служебный', 'code' => 'platform', 'monthly_price' => '1', 'grace_days' => '7']);
        $this->assertSame(0, (int) $this->pvalue("SELECT COUNT(*) FROM colleges WHERE code = 'platform'"), 'Reserved code');

        // Sign-in to each college; data stay in its own database.
        $a = $this->signIn('alpha', $alpha);
        $page = $a->get('/dashboard');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('href="/assets/css/app.css', $page['body'], 'Static files from the site root');
        $this->assertStringContainsString('content="/alpha/"', $page['body'], 'Links inside the college');
        $b = $this->signIn('beta', $beta);
        $this->assertSame(200, $b->get('/dashboard')['status']);
        $this->tenant('alpha')->exec("INSERT INTO departments (code, name_ru, name_kk) VALUES ('ALPHA-IT', 'Отделение Альфа', 'Альфа бөлімі')");
        $this->assertStringContainsString('Отделение Альфа', $a->get('/dictionaries/departments')['body']);
        $this->assertStringNotContainsString('Отделение Альфа', $b->get('/dictionaries/departments')['body'], 'Another college does not see the data');

        // A session cookie of one college does not work in another one.
        $sid = (string) $a->cookie('collegeos_session');
        $this->assertTrue($sid !== '', 'Session cookie');
        $stolen = (new Http($this->url()))->request('GET', '/beta/dashboard', null, ['Cookie: collegeos_session=' . $sid]);
        $this->assertSame('/beta/login', $stolen['location'], 'Session of alpha is refused by beta');
        $this->assertSame(200, $a->get('/dashboard')['status'], 'Still signed in to alpha');
        $panel = (new Http($this->url()))->request('GET', '/platform/', null, ['Cookie: collegeos_session=' . $sid]);
        $this->assertSame('/platform/login', $panel['location'], 'A college session does not open the owner panel');

        // Owner reports and CSV.
        $this->assertStringContainsString('Колледж Альфа', $owner->get('/reports')['body']);
        $csv = $owner->get('/?format=csv');
        $this->assertStringContainsString('text/csv', $csv['type']);
        $this->assertStringContainsString('Колледж Бета', $csv['body']);
    }

    public function testSubscriptionInvoicesAndSuspension(): void
    {
        $owner = $this->owner();
        $password = $this->college($owner, 'gamma', 'Колледж Гамма');
        $id = (int) $this->pvalue("SELECT id FROM colleges WHERE code = 'gamma'");
        $set = fn (string $sql, array $p) => $this->platform()->prepare($sql)->execute($p);
        $users = fn (): int => (int) $this->tenant('gamma')->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $http = $this->signIn('gamma', $password);
        $userForm = ['last_name' => 'Новиков', 'first_name' => 'Пётр', 'login' => 'novikov.p', 'locale' => 'ru', 'roles' => [(int) $this->tenant('gamma')->query("SELECT id FROM roles WHERE slug = 'teacher'")->fetchColumn()]];

        // Expiring: the administrator sees a reminder, everything works.
        $set('UPDATE colleges SET paid_until = CURDATE() + INTERVAL 5 DAY, status = ? WHERE id = ?', ['active', $id]);
        $this->assertStringContainsString('Подписка оплачена до', $http->get('/dashboard')['body']);
        // Grace period: still full access.
        $set('UPDATE colleges SET paid_until = CURDATE() - INTERVAL 3 DAY, grace_days = 7 WHERE id = ?', [$id]);
        $this->assertStringContainsString('Срок подписки истёк', $http->get('/dashboard')['body']);
        $before = $users();
        $http->submit('/users', $userForm);
        $this->assertSame($before + 1, $users(), 'Changes are saved during the grace period');

        // Read-only: pages open, changes are refused, sign-in still works.
        $set('UPDATE colleges SET paid_until = CURDATE() - INTERVAL 30 DAY WHERE id = ?', [$id]);
        $page = $http->get('/dashboard');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('режим только просмотра', $page['body']);
        $http->submit('/users', ['login' => 'novikov.q'] + $userForm);
        $this->assertSame($before + 1, $users(), 'Nothing is saved in read-only mode');
        $this->assertSame(402, $http->json('POST', '/api/document-requests', [])['status'], 'API refuses changes too');
        $this->assertSame(200, (new Http($this->url(), '/gamma'))->login('admin')->get('/dashboard')['status'], 'Sign-in works in read-only mode');

        // Invoice → paid: the term starts on the payment date (the old one has ended) and lasts the paid months.
        $owner->submit("/colleges/{$id}/invoices", ['months' => '6', 'issued_on' => date('Y-m-d')]);
        $invoice = (int) $this->pvalue('SELECT MAX(id) FROM college_invoices WHERE college_id = ?', [$id]);
        $this->assertSame('300000.00', (string) $this->pvalue('SELECT amount FROM college_invoices WHERE id = ?', [$invoice]), 'Price × months');
        $this->assertMatchesRegularExpression('/^СЧ-\d{4}-\d{5}$/u', (string) $this->pvalue('SELECT number FROM college_invoices WHERE id = ?', [$invoice]));
        $this->assertStringContainsString('300 000,00', $owner->get("/invoices/{$invoice}?format=print")['body']);
        $owner->submit("/invoices/{$invoice}/pay", ['paid_on' => date('Y-m-d'), 'method' => 'bank']);
        $expected = (new \DateTimeImmutable('today'))->modify('+6 months')->modify('-1 day')->format('Y-m-d');
        $this->assertSame($expected, (string) $this->pvalue('SELECT paid_until FROM colleges WHERE id = ?', [$id]));
        $owner->submit("/invoices/{$invoice}/pay", ['paid_on' => date('Y-m-d'), 'method' => 'bank']);
        $this->assertSame($expected, (string) $this->pvalue('SELECT paid_until FROM colleges WHERE id = ?', [$id]), 'An invoice is paid once');
        $http->submit('/users', ['login' => 'novikov.r'] + $userForm);
        $this->assertSame($before + 2, $users(), 'Full access again');

        // A second invoice continues the paid term.
        $owner->submit("/colleges/{$id}/invoices", ['months' => '1', 'amount' => '45000', 'issued_on' => date('Y-m-d')]);
        $second = (int) $this->pvalue('SELECT MAX(id) FROM college_invoices WHERE college_id = ?', [$id]);
        $owner->submit("/invoices/{$second}/pay", ['paid_on' => date('Y-m-d'), 'method' => 'kaspi']);
        $this->assertSame((new \DateTimeImmutable($expected))->modify('+1 day')->modify('+1 month')->modify('-1 day')->format('Y-m-d'), (string) $this->pvalue('SELECT paid_until FROM colleges WHERE id = ?', [$id]));

        // Suspension: nothing opens, not even the sign-in page; resuming restores access.
        $owner->submit("/colleges/{$id}/status", ['status' => 'suspended']);
        $this->assertSame(403, $http->get('/dashboard')['status']);
        $this->assertSame(403, (new Http($this->url()))->get('/gamma/login')['status']);
        $owner->submit("/colleges/{$id}/status", ['status' => 'active']);
        $this->assertSame(200, $http->get('/dashboard')['status']);

        // New administrator password from the panel.
        $owner->submit("/colleges/{$id}/admin-password", []);
        $this->assertSame(1, (int) $this->tenant('gamma')->query("SELECT must_change_password FROM users WHERE login = 'admin'")->fetchColumn());
        $this->assertTrue((int) $this->pvalue("SELECT COUNT(*) FROM platform_audit WHERE college_id = ? AND action = 'invoice.paid'", [$id]) === 2, 'Owner audit');
    }

    public function testOwnerManagesCollegeUsersPlansAnnouncementsBackups(): void
    {
        $owner = $this->owner();
        $this->college($owner, 'delta', 'Колледж Дельта');
        $id = (int) $this->pvalue("SELECT id FROM colleges WHERE code = 'delta'");
        $tenant = $this->tenant('delta');
        $tenant->exec("UPDATE users SET must_change_password = 1 WHERE login = 'admin'");
        $adminId = (int) $tenant->query("SELECT id FROM users WHERE login = 'admin'")->fetchColumn();

        // Entering the college: a one-time link signs in as its administrator, with a banner and an audit entry.
        $r = $owner->request('POST', "/platform/colleges/{$id}/impersonate", ['user_id' => $adminId]);
        $this->assertTrue(str_starts_with($r['location'], '/delta/owner-login'), 'Link into the college: ' . $r['location']);
        preg_match('/token=([a-f0-9]{64})/', $r['redirect'], $m);
        $token = $m[1] ?? '';
        $this->assertSame(64, strlen($token));
        $visitor = new Http($this->url(), '/delta');
        $visitor->get('/owner-login?token=' . $token);
        $page = $visitor->get('/dashboard');
        $this->assertSame(200, $page['status'], 'Signed in, no forced password change for the owner');
        $this->assertStringContainsString('Вы вошли как владелец платформы', $page['body']);
        $this->assertSame(1, (int) $tenant->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.owner_login'")->fetchColumn(), 'Entry in the college audit');
        $again = new Http($this->url(), '/delta');
        $again->get('/owner-login?token=' . $token);
        $this->assertSame('/delta/login', $again->get('/dashboard')['location'], 'A link works once');

        // Users of the college: add an administrator, block, new password.
        $owner->submit("/platform/colleges/{$id}/users", ['last_name' => 'Сейтова', 'first_name' => 'Айгуль', 'login' => 'seitova.a', 'role' => 'admin']);
        $newId = (int) $tenant->query("SELECT id FROM users WHERE login = 'seitova.a'")->fetchColumn();
        $this->assertTrue($newId > 0, 'Administrator added');
        $this->assertSame('admin', $tenant->query("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = {$newId}")->fetchColumn());
        $this->assertStringContainsString('seitova.a', $owner->get("/platform/colleges/{$id}/users?q=seitova")['body']);
        $owner->submit("/platform/colleges/{$id}/users/{$newId}/active", ['active' => '0']);
        $this->assertSame(0, (int) $tenant->query("SELECT is_active FROM users WHERE id = {$newId}")->fetchColumn());
        $tenant->exec("INSERT INTO users (login, password_hash, last_name, first_name) VALUES ('stud.x', 'x', 'Студент', 'Икс')");
        $studId = (int) $tenant->lastInsertId();
        $tenant->exec("INSERT INTO user_roles (user_id, role_id) SELECT {$studId}, id FROM roles WHERE slug = 'student'");
        $owner->request('POST', "/platform/colleges/{$id}/impersonate", ['user_id' => $studId]);
        $this->assertSame(0, (int) $this->pvalue('SELECT COUNT(*) FROM college_login_tokens WHERE user_id = ? AND college_id = ?', [$studId, $id]), 'Only administrators can be entered as');

        // Tariff limit of active students.
        $owner->submit('/platform/plans', ['name' => 'Мини', 'max_students' => '1', 'monthly_price' => '10000']);
        $plan = (int) $this->pvalue("SELECT id FROM plans WHERE name = 'Мини'");
        $college = $this->platform()->query("SELECT * FROM colleges WHERE id = {$id}")->fetch();
        $owner->submit("/platform/colleges/{$id}", ['_method' => 'PUT', 'name' => $college['name'], 'plan_id' => $plan, 'monthly_price' => '', 'grace_days' => '7', 'paid_until' => $college['paid_until']]);
        $this->assertSame('10000.00', (string) $this->pvalue('SELECT monthly_price FROM colleges WHERE id = ?', [$id]), 'Price from the tariff');
        $admin = $this->signIn('delta', $this->resetAdmin('delta'));
        $tenant->exec("INSERT INTO departments (code, name_ru, name_kk) VALUES ('D1', 'Отделение', 'Бөлім')");
        $tenant->exec("INSERT INTO specialties (department_id, code, name_ru, name_kk) VALUES (LAST_INSERT_ID(), '06130100', 'ПО', 'БҚ')");
        $tenant->exec("INSERT INTO `groups` (code, name, specialty_id, course, admission_year, academic_year_id)
                       SELECT 'ПО-26-1', 'ПО-26-1', LAST_INSERT_ID(), 1, 2026, (SELECT MIN(id) FROM academic_years)");
        $group = (int) $tenant->lastInsertId();
        $admin->submit('/students', ['last_name' => 'Первый', 'first_name' => 'Студент', 'locale' => 'ru', 'group_id' => $group]);
        $this->assertSame(1, (int) $tenant->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn());
        $admin->request('POST', '/students', ['last_name' => 'Второй', 'first_name' => 'Студент', 'locale' => 'ru', 'group_id' => $group]);
        $this->assertSame(1, (int) $tenant->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(), 'Limit of the tariff');
        $this->assertStringContainsString('лимит тарифа', mb_strtolower($admin->get('/students/create')['body']), 'The form explains the limit');

        // Announcements: for administrators only, or for everybody.
        $owner->submit('/platform/announcements', ['title' => 'Плановые работы в субботу', 'audience' => 'admins', 'level' => 'warning', 'starts_on' => date('Y-m-d')]);
        $owner->submit('/platform/announcements', ['title' => 'Новая версия', 'college_id' => $id, 'audience' => 'all', 'level' => 'info', 'starts_on' => date('Y-m-d', strtotime('+3 days'))]);
        $dashboard = $admin->get('/dashboard')['body'];
        $this->assertStringContainsString('Плановые работы в субботу', $dashboard);
        $this->assertStringNotContainsString('Новая версия', $dashboard, 'Not yet started');

        // Backup, archive, deletion of the database.
        $owner->submit("/platform/colleges/{$id}/archive", ['archive' => '1']);
        $this->assertSame(403, $admin->get('/dashboard')['status'], 'Archived college is closed');
        $owner->submit("/platform/colleges/{$id}/delete-database", ['confirm' => 'delta']);
        $this->assertSame(null, $this->pvalue('SELECT db_deleted_at FROM colleges WHERE id = ?', [$id]), 'No deletion without a fresh backup');
        $owner->submit("/platform/colleges/{$id}/backups", []);
        $name = basename((string) (glob(dirname(__DIR__, 2) . '/storage/private/backups/delta-*.sql.gz') ?: [''])[0]);
        $this->assertMatchesRegularExpression('/^delta-\d{8}-\d{6}\.sql\.gz$/', $name);
        $download = $owner->get("/platform/colleges/{$id}/backups/" . substr($name, 0, -7) . '/download');
        $sql = (string) gzdecode($download['body']);
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('seitova.a', $sql, 'Data is in the backup');
        $owner->submit("/platform/colleges/{$id}/delete-database", ['confirm' => 'wrong']);
        $this->assertSame(null, $this->pvalue('SELECT db_deleted_at FROM colleges WHERE id = ?', [$id]), 'Wrong code');
        $owner->submit("/platform/colleges/{$id}/delete-database", ['confirm' => 'delta']);
        $this->assertNotSame(null, $this->pvalue('SELECT db_deleted_at FROM colleges WHERE id = ?', [$id]));
        $this->assertSame(0, (int) Environment::server()->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . self::PREFIX . "delta'")->fetchColumn(), 'Database dropped');
        $this->assertSame(200, $owner->get("/platform/colleges/{$id}")['status'], 'The card stays (payments, backups)');
        foreach (glob(dirname(__DIR__, 2) . '/storage/private/backups/delta-*.sql.gz') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function resetAdmin(string $code): string
    {
        $this->tenant($code)->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE login = 'admin'")->execute([password_hash(Environment::PASSWORD, PASSWORD_DEFAULT)]);

        return Environment::PASSWORD;
    }
}
