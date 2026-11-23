<?php

declare(strict_types=1);

/**
 * BILIMATY console.
 *
 *   php cli.php db:create          Create the database (utf8mb4) if missing
 *   php cli.php migrate            Run pending migrations
 *   php cli.php migrate:status     List migrations
 *   php cli.php migrate:rollback   Roll back the last batch
 *   php cli.php migrate:fresh --force [--seed]   Drop ALL tables and migrate again
 *   php cli.php db:seed [--demo]   Run seeders (idempotent); --demo adds test data (not in production)
 *   php cli.php user:password <login>   Set a new random password for a user
 *   php cli.php db:check     Check the database structure (engine, charset, keys, indexes, migrations)
 *   php cli.php check        Check production readiness (PHP, extensions, folders, .env security)
 *   php cli.php hr:daily     Close accounts of employees whose dismissal date has come (run daily from cron)
 *
 * Several colleges (TENANCY=true in .env):
 *   php cli.php platform:install         Create the platform database and the first owner account
 *   php cli.php platform:migrate         Run pending platform migrations
 *   php cli.php platform:admin <login>   Create an owner account or give it a new temporary password
 *   php cli.php tenants:list             Colleges, databases and subscription state
 *   php cli.php tenants:migrate          Migrate every college database and sync roles/permissions
 *   php cli.php <command> --college=<code>   Run a college command (migrate, db:seed, user:password…) for one college
 *   php cli.php hr:daily                 Without --college: for every college
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$command = $argv[1] ?? 'help';
$options = array_slice($argv, 2);
$out = static function (string $line): void {
    fwrite(STDOUT, $line . PHP_EOL);
};

// Several colleges: --college=<code> points every college command at that college's database.
$collegeCode = null;
foreach ($options as $i => $option) {
    if (str_starts_with($option, '--college=')) {
        $collegeCode = substr($option, 10);
        unset($options[$i]);
    }
}
$options = array_values($options);
if ($collegeCode !== null) {
    if (!App\Core\Tenant::enabled()) {
        $out('--college works only with TENANCY=true.');
        exit(1);
    }
    $college = App\Platform\Platform::collegeByCode($collegeCode);
    if ($college === null) {
        $out("College '{$collegeCode}' not found.");
        exit(1);
    }
    Config::set('database.connections.mysql.database', (string) $college['db_name']);
    App\Core\Tenant::set($college, '');
    $out("College: {$college['name']} ({$college['db_name']})");
}
/** Run $task for every college database (TENANCY), or once for the current database. */
$forEachCollege = static function (callable $task) use ($out, $collegeCode): void {
    if (!App\Core\Tenant::enabled() || $collegeCode !== null) {
        $task();

        return;
    }
    foreach (App\Platform\Platform::colleges() as $college) {
        $out("== {$college['code']} ({$college['db_name']})");
        Config::set('database.connections.mysql.database', (string) $college['db_name']);
        Database::setConnection(null);
        App\Core\Tenant::set($college, '');
        try {
            $task();
        } catch (Throwable $e) {
            $out('   FAILED: ' . $e->getMessage());
        }
    }
    Database::setConnection(null);
};
$migrator = static fn (): Migrator => new Migrator(Database::connection(), __DIR__ . '/database/migrations');
$seed = static function () use ($out): void {
    $pdo = Database::connection();
    foreach (require __DIR__ . '/database/seeders/DatabaseSeeder.php' as $seeder) {
        (require __DIR__ . "/database/seeders/{$seeder}.php")($pdo, $out);
    }
};

switch ($command) {
    case 'db:create':
        $config = Config::get('database.connections.mysql');
        $pdo = Database::connect($config, false);
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE %s',
            Database::identifier((string) $config['database']),
            $config['collation']
        ));
        $out("Database '{$config['database']}' is ready.");
        break;

    case 'migrate':
        $migrator()->migrate($out);
        break;

    case 'migrate:status':
        $m = $migrator();
        $ran = $m->ran();
        foreach ($m->files() as $name) {
            $out(sprintf('%-8s %s', isset($ran[$name]) ? "[{$ran[$name]}]" : '[ ]', $name));
        }
        break;

    case 'migrate:rollback':
        $migrator()->rollback($out);
        break;

    case 'migrate:fresh':
        if (!in_array('--force', $options, true)) {
            $out('This drops ALL tables of ' . Config::get('database.connections.mysql.database') . '. Re-run with --force.');
            exit(1);
        }
        $m = $migrator();
        $m->dropAllTables();
        $out('All tables dropped.');
        $m->migrate($out);
        if (in_array('--seed', $options, true)) {
            $seed();
        }
        break;

    case 'db:seed':
        $seed();
        if (in_array('--demo', $options, true)) {
            // One transaction: a failure leaves no half-created demo data.
            foreach (['DemoSeeder', 'DemoProcessSeeder', 'DemoScheduleSeeder', 'DemoJournalSeeder', 'DemoWorkloadSeeder', 'DemoRolesSeeder', 'DemoShowcaseSeeder', 'DemoPracticeSeeder'] as $demo) {
                Database::transaction(static fn () => (require __DIR__ . "/database/seeders/{$demo}.php")(Database::connection(), $out));
            }
        }
        break;

    case 'user:password':
        $login = $options[0] ?? '';
        if ($login === '') {
            $out('Usage: php cli.php user:password <login>');
            exit(1);
        }
        $password = App\Services\PasswordGenerator::generate(16);
        $updated = Database::execute(
            'UPDATE users SET password_hash = ?, password_changed_at = NOW(), must_change_password = 1
             WHERE login = ? AND deleted_at IS NULL',
            [password_hash($password, PASSWORD_DEFAULT), $login]
        );
        $out($updated > 0 ? "New password for '{$login}': {$password}" : "User '{$login}' not found.");
        break;

    case 'hr:daily':
        $forEachCollege(static function () use ($out): void {
            $closed = (new App\Services\HrService())->applyDueDismissals();
            $out("Accounts closed: {$closed}");
        });
        break;

    case 'platform:install':
    case 'platform:migrate':
        if (!App\Core\Tenant::enabled()) {
            $out('Set TENANCY=true in .env first.');
            exit(1);
        }
        App\Platform\Platform::createDatabase(App\Platform\Platform::database());
        App\Platform\Platform::migrator()->migrate($out);
        $out('Platform database: ' . App\Platform\Platform::database());
        if ($command === 'platform:install' && (int) App\Platform\Platform::pdo()->query('SELECT COUNT(*) FROM platform_admins')->fetchColumn() === 0) {
            $login = $options[0] ?? 'owner';
            $password = App\Services\PasswordGenerator::generate(16);
            App\Platform\Platform::pdo()->prepare('INSERT INTO platform_admins (login, password_hash, name) VALUES (?, ?, ?)')
                ->execute([$login, password_hash($password, PASSWORD_DEFAULT), 'Владелец']);
            $out("Owner '{$login}' created. Temporary password (shown once, change it at first sign-in): {$password}");
        }
        break;

    case 'platform:admin':
        $login = $options[0] ?? '';
        if ($login === '' || !App\Core\Tenant::enabled()) {
            $out('Usage (TENANCY=true): php cli.php platform:admin <login>');
            exit(1);
        }
        $pdo = App\Platform\Platform::pdo();
        $password = App\Services\PasswordGenerator::generate(16);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO platform_admins (login, password_hash, name) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), must_change_password = 1, is_active = 1')
            ->execute([$login, $hash, $login]);
        $out("Owner '{$login}': temporary password {$password}");
        break;

    case 'tenants:list':
        foreach (App\Platform\Platform::colleges() as $c) {
            $out(sprintf('%-20s %-28s %-12s %-10s %s', $c['code'], $c['db_name'], (string) $c['paid_until'], App\Core\Tenant::state($c), $c['name']));
        }
        break;

    case 'tenants:migrate':
        foreach (App\Platform\Platform::colleges() as $c) {
            $out("== {$c['code']} ({$c['db_name']})");
            try {
                $pdo = App\Platform\Platform::tenantPdo((string) $c['db_name']);
                App\Platform\Platform::tenantMigrator($pdo)->migrate($out);
                App\Platform\Platform::seedTenant($pdo, $out);
            } catch (Throwable $e) {
                $out('   FAILED: ' . $e->getMessage());
            }
        }
        break;

    case 'db:check':
    case 'check':
        $checker = new App\Services\SystemCheck(__DIR__);
        $lines = $command === 'check' ? $checker->environment() : $checker->schema();
        $failed = 0;
        foreach ($lines as [$level, $message]) {
            $out(str_pad('[' . $level . ']', 7) . ' ' . $message);
            $failed += $level === 'FAIL' ? 1 : 0;
        }
        exit($failed > 0 ? 1 : 0);

    default:
        preg_match('#/\*\*(.*?)\*/#s', (string) file_get_contents(__FILE__), $m);
        $out(trim(preg_replace('/^\s*\* ?/m', '', $m[1] ?? 'BILIMATY console') ?? ''));
}
