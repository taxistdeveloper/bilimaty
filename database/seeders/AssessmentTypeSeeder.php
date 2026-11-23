<?php

declare(strict_types=1);

/**
 * Base kinds of results named in the specification (current grades, rating 1, rating 2,
 * exam, final). Weights are NOT set here: the administration defines them in grading
 * schemes. Existing rows keep their names (only missing codes are inserted).
 */
return static function (PDO $pdo, callable $out): void {
    $types = [
        // code, ru, kk, short ru, short kk, source, system, order
        ['current', 'Текущая успеваемость', 'Ағымдағы үлгерім', 'Тек.', 'Ағым.', 'lessons_average', 1, 10],
        ['rating1', 'Рейтинг 1', '1-рейтинг', 'Р1', 'Р1', 'manual', 1, 20],
        ['rating2', 'Рейтинг 2', '2-рейтинг', 'Р2', 'Р2', 'manual', 1, 30],
        ['exam', 'Экзамен', 'Емтихан', 'Экз.', 'Емт.', 'manual', 1, 40],
        ['final', 'Итоговая оценка', 'Қорытынды баға', 'Итог', 'Қор.', 'computed_final', 1, 90],
    ];
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO assessment_types (code, name_ru, name_kk, short_ru, short_kk, source, is_system, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $added = 0;
    foreach ($types as $row) {
        $insert->execute($row);
        $added += $insert->rowCount();
    }
    $out("Assessment types: {$added} added.");
};
