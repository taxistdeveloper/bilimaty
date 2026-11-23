<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use Tests\TestCase;

/**
 * Base for tests against the isolated test database and the test server.
 * Demo data (cli.php db:seed --demo) gives groups, students, teachers, journals and plans.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function db(): PDO
    {
        return Environment::pdo();
    }

    protected function value(string $sql, array $params = []): mixed
    {
        $s = $this->db()->prepare($sql);
        $s->execute($params);

        return $s->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    protected function rows(string $sql, array $params = []): array
    {
        $s = $this->db()->prepare($sql);
        $s->execute($params);

        return $s->fetchAll();
    }

    /** A user that can sign in with the test password (created with the role if missing). */
    protected function user(string $login, ?string $role = null): string
    {
        $hash = password_hash(Environment::PASSWORD, PASSWORD_DEFAULT);
        if ($this->value('SELECT id FROM users WHERE login = ?', [$login]) === false) {
            $this->db()->prepare("INSERT INTO users (login, password_hash, last_name, first_name, is_active, locale) VALUES (?, ?, 'Тест', ?, 1, 'ru')")
                ->execute([$login, $hash, (string) $role]);
            $this->db()->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?')->execute([(int) $this->db()->lastInsertId(), $role]);
        }
        $this->db()->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() - INTERVAL 1 MINUTE, locale = 'ru', is_active = 1 WHERE login = ?")
            ->execute([$hash, $login]);
        $this->db()->prepare('DELETE FROM login_attempts')->execute();

        return $login;
    }

    protected function as(string $login, ?string $role = null): Http
    {
        return (new Http())->login($this->user($login, $role));
    }

    protected function studentId(string $login): int
    {
        return (int) $this->value('SELECT s.id FROM students s JOIN users u ON u.id = s.user_id WHERE u.login = ?', [$login]);
    }
}
