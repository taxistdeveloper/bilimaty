<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Converts a journal final into the grades used in college reports: the point-rating percentage,
 * the letter and digital equivalent of config/transcript.php and the traditional five-point mark
 * (5 «отлично», 4 «хорошо», 3 «удовлетворительно», 2 «неудовлетворительно»).
 *
 * A scheme scored up to 5 is already traditional: its final is rounded to the mark.
 * Any other scheme is converted to a percentage of its range and looked up in the letter scale;
 * the mark follows the digital equivalent (≥ 3.67 → 5, ≥ 2.67 → 4, ≥ 1.0 → 3), as on the transcript.
 * The scheme's pass threshold has the last word on «2».
 */
final class GradeScale
{
    /**
     * @param array{min: float, max: float}|null $scheme
     * @return array{mark: int, percent: ?float, letter: ?string, points: ?float}|null
     */
    public static function grade(mixed $final, ?array $scheme, ?bool $passed = null): ?array
    {
        if ($final === null || $final === '') {
            return null;
        }
        $final = (float) $final;
        $min = $scheme['min'] ?? 0.0;
        $max = $scheme['max'] ?? ($final <= 5 ? 5.0 : 100.0);

        if ($max <= 5) {
            $mark = max(2, min(5, (int) round($final)));
            $percent = null;
            $letter = null;
            $points = null;
        } else {
            $percent = $max > $min ? round(max(0.0, min(100.0, ($final - $min) / ($max - $min) * 100)), 1) : null;
            [$letter, $points] = self::letter($percent);
            $mark = $points === null ? self::markOfPercent($percent) : ($points >= 3.67 ? 5 : ($points >= 2.67 ? 4 : ($points >= 1.0 ? 3 : 2)));
        }
        if ($passed === false) {
            $mark = 2;
        } elseif ($passed === true && $mark < 3) {
            $mark = 3;
        }

        return ['mark' => $mark, 'percent' => $percent, 'letter' => $letter, 'points' => $points];
    }

    /**
     * @param list<int> $marks five-point marks
     * @return array{graded: int, counts: array<int, int>, absolute: ?float, quality: ?float, average: ?float}
     */
    public static function stats(array $marks): array
    {
        $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0];
        foreach ($marks as $mark) {
            $counts[$mark]++;
        }
        $n = count($marks);

        return [
            'graded' => $n,
            'counts' => $counts,
            'absolute' => $n > 0 ? round(($n - $counts[2]) / $n * 100, 1) : null,
            'quality' => $n > 0 ? round(($counts[5] + $counts[4]) / $n * 100, 1) : null,
            'average' => $n > 0 ? round(array_sum($marks) / $n, 2) : null,
        ];
    }

    /**
     * Group totals from each student's five-point marks. A student is excellent with only «5»,
     * good with «4»/«5», satisfactory with a «3», failing with any «2»; absolute performance and
     * quality are shares of graded students.
     *
     * @param array<string, list<int>> $marksByStudent student name => marks
     * @return array{students: int, graded: int, categories: array<string, list<string>>, absolute: ?float, quality: ?float, average: ?float}
     */
    public static function summary(array $marksByStudent): array
    {
        $categories = ['excellent' => [], 'good' => [], 'satisfactory' => [], 'failing' => [], 'ungraded' => []];
        $all = [];
        foreach ($marksByStudent as $name => $marks) {
            $categories[self::category($marks)][] = (string) $name;
            array_push($all, ...$marks);
        }
        $graded = count($marksByStudent) - count($categories['ungraded']);

        return [
            'students' => count($marksByStudent),
            'graded' => $graded,
            'categories' => $categories,
            'absolute' => $graded > 0 ? round(($graded - count($categories['failing'])) / $graded * 100, 1) : null,
            'quality' => $graded > 0 ? round((count($categories['excellent']) + count($categories['good'])) / $graded * 100, 1) : null,
            'average' => self::stats($all)['average'],
        ];
    }

    /** @param list<int> $marks */
    public static function category(array $marks): string
    {
        return $marks === [] ? 'ungraded' : match (min($marks)) {
            5 => 'excellent',
            4 => 'good',
            3 => 'satisfactory',
            default => 'failing',
        };
    }

    /** @return list<array{0: float, 1: string, 2: float}> letter scale, highest first ([] when disabled) */
    public static function scale(): array
    {
        $config = (array) Config::get('transcript.gpa', []);

        return !empty($config['enabled']) ? array_values((array) ($config['scale'] ?? [])) : [];
    }

    /** @return array{0: ?string, 1: ?float} */
    private static function letter(?float $percent): array
    {
        if ($percent === null) {
            return [null, null];
        }
        foreach (self::scale() as [$from, $letter, $points]) {
            if ($percent >= (float) $from) {
                return [(string) $letter, (float) $points];
            }
        }

        return [null, null];
    }

    /** Fallback when no letter scale is configured: 90 / 75 / 50 %. */
    private static function markOfPercent(?float $percent): int
    {
        return $percent === null ? 2 : ($percent >= 90 ? 5 : ($percent >= 75 ? 4 : ($percent >= 50 ? 3 : 2)));
    }
}
