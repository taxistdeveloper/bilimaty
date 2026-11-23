<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Final grade by the journal's grading scheme. No weights or formula are built in:
 *
 *   final = Σ (weight_i × value_i) / Σ weight_i,   i = components of the scheme
 *
 * where weights and the scale come from the scheme configured by the administration,
 * "current" components are the average of the student's lesson grades, and the result
 * is rounded half-up to the scheme's number of decimals. If any component value is
 * missing the final is not computed (incomplete data is never treated as zero).
 */
final class GradeCalculator
{
    /** Average of numeric lesson grades, or null when there are none. @param list<float|string|null> $grades */
    public static function average(array $grades): ?float
    {
        $values = array_map('floatval', array_filter($grades, static fn ($v): bool => $v !== null && $v !== ''));

        return $values === [] ? null : round(array_sum($values) / count($values), 2);
    }

    /**
     * @param array<string, mixed> $scheme with 'components' (code, weight, source) and final_decimals
     * @param array<string, float|null> $values component code => value (current already averaged)
     * @return array{value: ?float, missing: list<string>}
     */
    public static function final(array $scheme, array $values): array
    {
        $components = $scheme['components'] ?? [];
        if ($components === []) {
            return ['value' => null, 'missing' => []];
        }

        $sum = 0.0;
        $weights = 0.0;
        $missing = [];
        foreach ($components as $component) {
            $value = $values[$component['code']] ?? null;
            if ($value === null) {
                $missing[] = (string) $component['code'];
                continue;
            }
            $sum += (float) $component['weight'] * (float) $value;
            $weights += (float) $component['weight'];
        }
        if ($missing !== [] || $weights <= 0) {
            return ['value' => null, 'missing' => $missing];
        }

        return ['value' => round($sum / $weights, (int) $scheme['final_decimals'], PHP_ROUND_HALF_UP), 'missing' => []];
    }

    /** @param array<string, mixed> $scheme */
    public static function passed(array $scheme, ?float $final): ?bool
    {
        if ($final === null || $scheme['pass_threshold'] === null) {
            return null;
        }

        return $final >= (float) $scheme['pass_threshold'];
    }
}
