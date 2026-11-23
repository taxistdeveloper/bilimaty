<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SettingsRepository
{
    /** @return array<string, string|null> */
    public function all(): array
    {
        $rows = Database::fetchAll('SELECT `key`, `value` FROM settings');

        return array_column($rows, 'value', 'key');
    }

    public function set(string $key, ?string $value, string $type, ?int $userId): void
    {
        Database::execute(
            'INSERT INTO settings (`key`, `value`, `type`, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`), updated_by = VALUES(updated_by)',
            [$key, $value, $type, $userId]
        );
    }
}
