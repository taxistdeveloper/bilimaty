<?php

declare(strict_types=1);

/**
 * Academic years 2025–2029 with two semesters each.
 * Dates are initial values: the academic office adjusts them to the approved calendar.
 * Existing years are not modified. The year containing today becomes active
 * only if no year is active yet.
 */
return static function (PDO $pdo, callable $out): void {
    $today = date('Y-m-d');

    $pdo->beginTransaction();
    try {
        $hasActive = (int) $pdo->query("SELECT COUNT(*) FROM academic_years WHERE status = 'active'")->fetchColumn() > 0;
        $findYear = $pdo->prepare('SELECT id FROM academic_years WHERE title = ?');
        $insertYear = $pdo->prepare('INSERT INTO academic_years (title, start_date, end_date, status) VALUES (?, ?, ?, ?)');
        $insertSemester = $pdo->prepare(
            'INSERT IGNORE INTO semesters (academic_year_id, number, start_date, end_date) VALUES (?, ?, ?, ?)'
        );

        $created = 0;
        foreach ([2025, 2026, 2027, 2028] as $startYear) {
            $endYear = $startYear + 1;
            $title = "{$startYear}-{$endYear}";
            $start = "{$startYear}-09-01";
            $end = "{$endYear}-06-30";

            $findYear->execute([$title]);
            $yearId = $findYear->fetchColumn();
            if ($yearId === false) {
                $status = match (true) {
                    $end < $today => 'archived',
                    !$hasActive && $start <= $today && $today <= $end => 'active',
                    default => 'planned',
                };
                $hasActive = $hasActive || $status === 'active';
                $insertYear->execute([$title, $start, $end, $status]);
                $yearId = (int) $pdo->lastInsertId();
                $created++;
            }

            $insertSemester->execute([$yearId, 1, $start, "{$startYear}-12-31"]);
            $insertSemester->execute([$yearId, 2, "{$endYear}-01-12", $end]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $out("Academic years: {$created} created.");
};
