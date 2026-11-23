<?php

declare(strict_types=1);

/**
 * Transcript settings.
 *
 * Grading follows the point-rating letter system used in technical and vocational education
 * of the Republic of Kazakhstan (Типовые правила проведения текущего контроля успеваемости,
 * промежуточной и итоговой аттестации обучающихся — приказ МОН РК от 18.03.2008 № 125).
 *
 *   'scale' => [ [final from (inclusive, %), letter, digital equivalent], … ]  — highest first
 *   'weight_by' => 'credits' | 'hours'   — weight of each subject in the GPA average
 *                  ('credits' falls back to hours when any subject has no credits)
 *   'basis'     => text printed on the transcript
 */
return [
    'number_prefix' => 'T',
    'gpa' => [
        'enabled' => true,
        'scale' => [
            [95, 'A', 4.0],
            [90, 'A-', 3.67],
            [85, 'B+', 3.33],
            [80, 'B', 3.0],
            [75, 'B-', 2.67],
            [70, 'C+', 2.33],
            [65, 'C', 2.0],
            [60, 'C-', 1.67],
            [55, 'D+', 1.33],
            [50, 'D', 1.0],
            [0, 'F', 0.0],
        ],
        'weight_by' => 'credits',
        'decimals' => 2,
        'basis' => 'приказ МОН РК от 18.03.2008 № 125',
    ],
];
