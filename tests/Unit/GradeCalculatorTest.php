<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GradeCalculator;
use App\Services\JournalService;
use Tests\TestCase;

final class GradeCalculatorTest extends TestCase
{
    private function scheme(int $decimals = 0, ?string $threshold = '50'): array
    {
        return ['final_decimals' => $decimals, 'pass_threshold' => $threshold, 'components' => [
            ['code' => 'current', 'weight' => 40], ['code' => 'rating1', 'weight' => 15], ['code' => 'rating2', 'weight' => 15], ['code' => 'exam', 'weight' => 30],
        ]];
    }

    public function testWeightedFinalUsesOnlyTheScheme(): void
    {
        $r = GradeCalculator::final($this->scheme(2), ['current' => 80, 'rating1' => 70, 'rating2' => 90, 'exam' => 60]);
        // (40·80 + 15·70 + 15·90 + 30·60) / 100 = 74
        $this->assertSame(74.0, $r['value']);
        $this->assertSame([], $r['missing']);
    }

    public function testMissingComponentIsNeverTreatedAsZero(): void
    {
        $r = GradeCalculator::final($this->scheme(), ['current' => 80, 'rating1' => 70, 'rating2' => null]);
        $this->assertNull($r['value']);
        $this->assertSame(['rating2', 'exam'], $r['missing']);
    }

    public function testRoundingHalfUpAndThreshold(): void
    {
        $r = GradeCalculator::final(['final_decimals' => 0, 'pass_threshold' => '50', 'components' => [['code' => 'a', 'weight' => 1], ['code' => 'b', 'weight' => 1]]], ['a' => 49, 'b' => 50]);
        $this->assertSame(50.0, $r['value'], '49.5 rounds half up to 50');
        $this->assertTrue(GradeCalculator::passed($this->scheme(), 50.0));
        $this->assertFalse(GradeCalculator::passed($this->scheme(), 49.99));
        $this->assertNull(GradeCalculator::passed($this->scheme(0, null), 10.0), 'No threshold — no verdict');
    }

    public function testAverageAndNormalize(): void
    {
        $this->assertNull(GradeCalculator::average([null, '']));
        $this->assertSame(85.33, GradeCalculator::average(['80', 90, 86]));
        $this->assertSame('72.5', JournalService::normalize('72.50'));
        $this->assertSame('100', JournalService::normalize(100.0));
        $this->assertNull(JournalService::normalize(''));
    }
}
