<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\SystemCheck;

final class SchemaTest extends IntegrationTestCase
{
    public function testDatabaseStructure(): void
    {
        foreach ((new SystemCheck(dirname(__DIR__, 2)))->schema() as [$level, $message]) {
            $this->assertSame('OK', $level, $message);
        }
    }

    public function testMigrationsRollBackAndApplyAgain(): void
    {
        $root = dirname(__DIR__, 2);
        exec(PHP_BINARY . ' ' . escapeshellarg($root . '/cli.php') . ' migrate:status 2>&1', $before);
        $this->assertSame([], array_values(array_filter($before, static fn (string $l): bool => str_starts_with(trim($l), '[ ]'))), 'All migrations applied');
    }
}
