<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * Builds a Monday-first month grid with events and semester membership for each day.
 */
final class CalendarGrid
{
    /**
     * @param list<array<string, mixed>> $events     rows with start_date/end_date
     * @param list<array<string, mixed>> $semesters  rows with number/start_date/end_date
     * @return list<list<array{date: string, day: int, in_month: bool, today: bool, weekend: bool, semester: ?int, events: list<array<string, mixed>>}>>
     */
    public static function month(string $month, array $events, array $semesters): array
    {
        $first = new DateTimeImmutable($month . '-01');
        $last = $first->modify('last day of this month');
        $start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
        $end = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
        $today = date('Y-m-d');

        $weeks = [];
        $week = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $semester = null;
            foreach ($semesters as $s) {
                if ($s['start_date'] <= $date && $date <= $s['end_date']) {
                    $semester = (int) $s['number'];
                }
            }
            $week[] = [
                'date' => $date,
                'day' => (int) $day->format('j'),
                'in_month' => $day->format('Y-m') === $month,
                'today' => $date === $today,
                'weekend' => (int) $day->format('N') >= 6,
                'semester' => $semester,
                'events' => array_values(array_filter(
                    $events,
                    static fn (array $e): bool => $e['start_date'] <= $date && $date <= $e['end_date']
                )),
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /** @return list<string> months "Y-m" covered by the period */
    public static function months(string $start, string $end): array
    {
        $months = [];
        $cursor = new DateTimeImmutable(substr($start, 0, 7) . '-01');
        $stop = new DateTimeImmutable(substr($end, 0, 7) . '-01');
        while ($cursor <= $stop) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }

    /** Localized month name (nominative) for "Y-m". */
    public static function monthName(string $month): string
    {
        return __('calendar.months.' . (int) substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
    }
}
