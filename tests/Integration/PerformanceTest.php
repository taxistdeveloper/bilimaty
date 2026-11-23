<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Smoke check of response times of the heaviest pages on demo data (built-in single-thread server,
 * so production times are lower). Catches accidental N+1 queries and missing indexes.
 */
final class PerformanceTest extends IntegrationTestCase
{
    private const LIMIT_MS = 1500;

    public function testHeavyPagesRespondQuickly(): void
    {
        $admin = $this->as('admin');
        $journal = (int) $this->value('SELECT id FROM teacher_subject_assignments ORDER BY id LIMIT 1');
        $student = $this->studentId('qasymova.i');
        $slow = [];
        $times = [];
        foreach (['/dashboard', '/students', "/journal/{$journal}", '/schedule', '/workload', '/workload/reports?by=detail',
            "/students/{$student}/transcript", '/audit', '/exams', '/documents', '/surveys', '/curricula'] as $page) {
            $admin->get($page); // warm up the opcode cache
            $t = microtime(true);
            $status = $admin->get($page)['status'];
            $ms = (int) ((microtime(true) - $t) * 1000);
            $this->assertSame(200, $status, $page);
            $times[] = $ms;
            if ($ms > self::LIMIT_MS) {
                $slow[] = "{$page}: {$ms} ms";
            }
        }
        sort($times);
        echo ' (max ' . max($times) . ' ms, median ' . $times[intdiv(count($times), 2)] . ' ms) ';
        $this->assertSame([], $slow, 'Slower than ' . self::LIMIT_MS . ' ms');
    }
}
