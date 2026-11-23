<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Csv;
use App\Helpers\QrCode;
use App\Services\DocumentService;
use App\Services\LoginGenerator;
use App\Services\WorkloadService;
use Tests\TestCase;

final class HelpersTest extends TestCase
{
    public function testQrMatrixSizeAndFinderPatterns(): void
    {
        foreach (['https://college.kz/verify/' . str_repeat('a', 32), 'x', str_repeat('Z', 150)] as $data) {
            $m = QrCode::matrix($data);
            $size = count($m);
            $this->assertSame(0, ($size - 17) % 4, 'Size must be 17 + 4·version');
            // finder pattern: 7×7 dark border with a dark 3×3 centre in three corners
            foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
                $this->assertTrue((bool) $m[$r][$c] && (bool) $m[$r + 6][$c + 6] && (bool) $m[$r + 3][$c + 3] && !(bool) $m[$r + 1][$c + 1], "Finder pattern at {$r},{$c}");
            }
        }
        $this->assertSame(QrCode::matrix('stable'), QrCode::matrix('stable'), 'Deterministic output');
        $svg = QrCode::svg('test <b>', 4, 'QR "label"');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<b>', $svg, 'Data is not echoed into the SVG');
        $this->assertThrows(\InvalidArgumentException::class, fn () => QrCode::matrix(str_repeat('x', 1000)));
    }

    public function testCsvIsExcelFriendlyAndNeutralisesFormulas(): void
    {
        $r = Csv::download([['=SUM(A1)', '+7', '-3', '@cmd', 'обычный', 5]], 'a b.csv');
        $body = $r->content();
        $this->assertTrue(str_starts_with($body, "\xEF\xBB\xBF"), 'UTF-8 BOM');
        $this->assertStringContainsString("'=SUM(A1);'+7;'-3;'@cmd;обычный;5", $body);
        $this->assertStringContainsString('filename="a_b.csv"', (string) $r->header('Content-Disposition'));
    }

    public function testLoginTransliterationAndHours(): void
    {
        $this->assertSame('qasymova', LoginGenerator::transliterate('Қасымова'));
        $this->assertSame('shchukin', LoginGenerator::transliterate('Щукин'));
        $this->assertSame('ozgenu', LoginGenerator::transliterate('Өзгеңү'));
        $this->assertSame('48', WorkloadService::hours(48.0));
        $this->assertSame('1.5', WorkloadService::hours('1.50'));
        $this->assertSame('—', WorkloadService::hours(null));
    }

    public function testAttachmentPathAcceptsOnlyGeneratedNames(): void
    {
        $this->assertNotNull(DocumentService::path(str_repeat('a', 32) . '.pdf'));
        foreach (['../../.env', str_repeat('a', 32) . '.php', 'x.pdf', str_repeat('a', 32) . '.pdf/../x'] as $bad) {
            $this->assertNull(DocumentService::path($bad), "Rejected: {$bad}");
        }
    }
}
