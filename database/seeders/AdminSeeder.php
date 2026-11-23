<?php

declare(strict_types=1);

use App\Core\Env;
use App\Services\PasswordGenerator;

/**
 * Creates the first Super Admin account from SEED_ADMIN_* variables.
 * If SEED_ADMIN_PASSWORD is empty a random password is generated and printed once.
 * An existing account is never modified.
 */
return static function (PDO $pdo, callable $out): void {
    $login = (string) (Env::get('SEED_ADMIN_LOGIN') ?: 'admin');
    $email = Env::get('SEED_ADMIN_EMAIL') ?: null;

    $exists = $pdo->prepare('SELECT id FROM users WHERE login = ?');
    $exists->execute([$login]);
    if ($exists->fetchColumn() !== false) {
        $out("Admin: user '{$login}' already exists, unchanged.");

        return;
    }

    $password = (string) (Env::get('SEED_ADMIN_PASSWORD') ?: '');
    $generated = $password === '';
    if ($generated) {
        $password = PasswordGenerator::generate(16);
    } elseif (strlen($password) < 10) {
        throw new RuntimeException('SEED_ADMIN_PASSWORD must contain at least 10 characters.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO users (login, email, password_hash, last_name, first_name, locale, password_changed_at, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)'
        )->execute([$login, $email, password_hash($password, PASSWORD_DEFAULT), 'Системный', 'Администратор', 'ru']);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = 'super_admin'"
        )->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $out("Admin: created super admin '{$login}'.");
    if ($generated) {
        $out("Admin: generated password (shown once, must be changed at first login): {$password}");
    }
};
