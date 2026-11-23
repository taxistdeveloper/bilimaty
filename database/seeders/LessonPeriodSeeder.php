<?php

declare(strict_types=1);

use App\Core\Config;

/**
 * Default bell schedule (config/schedule.php → default_periods), only when the
 * table is empty: the college edits the times in Dictionaries → Bell schedule.
 */
return static function (PDO $pdo, callable $out): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM lesson_periods')->fetchColumn() > 0) {
        $out('Lesson periods: already configured, unchanged.');

        return;
    }
    $insert = $pdo->prepare('INSERT INTO lesson_periods (number, start_time, end_time) VALUES (?, ?, ?)');
    foreach ((array) Config::get('schedule.default_periods', []) as $number => [$start, $end]) {
        $insert->execute([$number, $start, $end]);
    }
    $out('Lesson periods: default bell schedule created.');
};
