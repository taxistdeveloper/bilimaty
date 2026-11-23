<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Static checks of the source code (SQL safety, input handling, dangerous functions).
 */
final class SecurityScanTest extends TestCase
{
    /** @return array<string, string> path => source */
    private function sources(string $dir): array
    {
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->getExtension() === 'php') {
                $out[substr($file->getPathname(), strlen(dirname(__DIR__, 2)) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }

        return $out;
    }

    public function testSuperglobalsOnlyInTheCore(): void
    {
        $allowed = ['app/Core/Request.php', 'app/Core/Session.php', 'app/Core/ErrorHandler.php', 'app/Core/Env.php', 'app/Helpers/functions.php', 'app/Controllers/TranscriptController.php'];
        foreach ($this->sources(dirname(__DIR__, 2) . '/app') as $path => $code) {
            $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code);
            if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|FILES|SERVER)\b/', (string) $code) === 1) {
                $this->assertContains($path, $allowed, "Superglobal used in {$path}");
            }
        }
    }

    public function testNoRequestInputInsideSqlStrings(): void
    {
        foreach ($this->sources(dirname(__DIR__, 2) . '/app') as $path => $code) {
            foreach (explode("\n", $code) as $n => $line) {
                if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|WHERE)\b/', $line) && preg_match('/\$request->|\$_(GET|POST)/', $line)) {
                    $this->check(false, "Request data inside SQL at {$path}:" . ($n + 1));
                }
            }
            $this->check(true, '');
        }
    }

    public function testNoDangerousFunctionsOrWeakHashes(): void
    {
        foreach ($this->sources(dirname(__DIR__, 2) . '/app') as $path => $code) {
            $code = (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $code);
            $this->assertSame(0, preg_match('/(?<![>:\w])(eval|exec|shell_exec|system|passthru|popen|unserialize)\s*\(/', $code), "Dangerous call in {$path}");
            $this->assertSame(0, preg_match('/\b(md5|sha1)\s*\(/', $code), "Weak hash in {$path}");
        }
    }

    public function testPasswordsAreHashedWithPasswordHash(): void
    {
        foreach ($this->sources(dirname(__DIR__, 2) . '/app') as $path => $code) {
            if (str_contains($code, 'password_hash')) {
                $this->assertSame(0, preg_match("/'password_hash'\\s*=>\\s*\\$(?!.*password_hash)/", $code), "Plain value written to password_hash in {$path}");
            }
        }
    }

    public function testViewsHaveNoSql(): void
    {
        foreach ($this->sources(dirname(__DIR__, 2) . '/app/Views') as $path => $code) {
            $this->assertSame(0, preg_match('/Database::|->prepare\(|\bPDO\b/', $code), "SQL in view {$path}");
        }
    }
}
