<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Repositories\GuardianRepository;
use App\Services\AccountService;

/**
 * Demo accounts for the roles the other demo seeders do not create (administrator, deputy director,
 * academic office, HR, parent), so the "view as role" switcher can show every role.
 * The parent is linked to the first demo student. Passwords are random; the switcher needs none.
 *
 * Run: php cli.php db:seed --demo   (refused when APP_ENV=production; runs once)
 */
return static function (PDO $pdo, callable $out): void {
    if (Env::get('APP_ENV') === 'production') {
        throw new RuntimeException('Demo data cannot be seeded when APP_ENV=production.');
    }
    if (Database::fetchColumn("SELECT `value` FROM settings WHERE `key` = 'demo_roles_seeded'") === '1') {
        $out('Demo roles: already seeded, skipped.');

        return;
    }

    $accounts = new AccountService();
    $people = [
        ['admin', 'Ахметова', 'Гульнара', 'Сериковна', 'ru'],
        ['deputy_director', 'Жакупов', 'Ерлан', 'Болатович', 'ru'],
        ['academic_office', 'Ислямова', 'Сауле', 'Нурлановна', 'kk'],
        ['hr', 'Беркимбаева', 'Алия', 'Маратовна', 'ru'],
    ];
    foreach ($people as [$role, $last, $first, $middle, $locale]) {
        if (Database::fetchColumn('SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = ? LIMIT 1', [$role]) !== null) {
            continue;
        }
        $accounts->create(['last_name' => $last, 'first_name' => $first, 'middle_name' => $middle, 'locale' => $locale], $role);
        $out("Demo roles: {$role} account created.");
    }

    $hasParent = Database::fetchColumn("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'parent' LIMIT 1") !== null;
    $studentId = Database::fetchColumn('SELECT id FROM students ORDER BY id LIMIT 1');
    if (!$hasParent && $studentId !== null) {
        $student = Database::fetch('SELECT u.last_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$studentId]);
        $last = (string) ($student['last_name'] ?? 'Иванова');
        // A mother shares the family name in its feminine form.
        if (preg_match('/(ов|ев|ин)$/u', $last) === 1) {
            $last .= 'а';
        }
        $account = $accounts->create(['last_name' => $last, 'first_name' => 'Сауле', 'middle_name' => null, 'locale' => 'ru'], 'parent');
        (new GuardianRepository())->link((int) $studentId, $account['user_id'], 'mother', null);
        $out('Demo roles: parent account created and linked to a student.');
    }

    Database::execute("INSERT INTO settings (`key`, `value`, `type`) VALUES ('demo_roles_seeded', '1', 'bool') ON DUPLICATE KEY UPDATE `value` = '1'");
};
