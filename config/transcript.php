<?php

declare(strict_types=1);

/**
 * Transcript settings.
 *
 * GPA is shown ONLY when the college has an approved scale and calculation rule.
 * Keep 'enabled' => false until the scale below is filled from the approved document.
 *
 *   'scale' => [
 *       // final grade from (inclusive) => [letter, grade points]   — highest first
 *       // e.g. [MIN_FINAL, 'LETTER', POINTS], …  (values come from the college's regulation)
 *   ],
 *   'weight_by' => 'credits' | 'hours'   — weight of each subject in the GPA average
 *   'basis'     => text printed on the transcript (number and date of the approving order)
 */
return [
    'number_prefix' => 'T',
    'gpa' => [
        'enabled' => false,
        'scale' => [],
        'weight_by' => 'credits',
        'decimals' => 2,
        'basis' => '',
    ],
];
