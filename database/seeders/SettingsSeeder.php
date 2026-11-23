<?php

declare(strict_types=1);

/**
 * Default system settings. Existing values are never overwritten.
 */
return static function (PDO $pdo, callable $out): void {
    $defaults = [
        ['college_name_ru', 'Колледж', 'string'],
        ['college_name_kk', 'Колледж', 'string'],
        ['default_locale', 'ru', 'string'],
        ['journal_lock_after_approval', '1', 'bool'],
    ];

    $insert = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`, `type`) VALUES (?, ?, ?)');
    foreach ($defaults as $row) {
        $insert->execute($row);
    }

    $out('Settings: defaults ensured.');
};
