<?php

declare(strict_types=1);

namespace Tests;

use Throwable;

/**
 * Minimal test base with PHPUnit-style assertions (no external dependencies: Composer packages
 * cannot be downloaded in every college network). Method names match PHPUnit, so the tests can
 * move to PHPUnit later without rewriting.
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    protected function assertTrue(mixed $value, string $message = ''): void
    {
        $this->check($value === true, $message ?: 'Expected true, got ' . $this->export($value));
    }

    protected function assertFalse(mixed $value, string $message = ''): void
    {
        $this->check($value === false, $message ?: 'Expected false, got ' . $this->export($value));
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->check($expected === $actual, $message ?: 'Expected ' . $this->export($expected) . ', got ' . $this->export($actual));
    }

    protected function assertNotSame(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        $this->check($unexpected !== $actual, $message !== '' ? $message : 'Value must differ from ' . var_export($unexpected, true));
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->check($expected == $actual, $message ?: 'Expected ' . $this->export($expected) . ', got ' . $this->export($actual));
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->check($value === null, $message ?: 'Expected null, got ' . $this->export($value));
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $this->check($value !== null, $message ?: 'Expected a value, got null');
    }

    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        $this->check(count($array) === $expected, $message ?: "Expected {$expected} items, got " . count($array));
    }

    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        $this->check(in_array($needle, $haystack, true), $message ?: $this->export($needle) . ' not found in ' . $this->export($haystack));
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->check(str_contains($haystack, $needle), $message ?: "Text does not contain «{$needle}»");
    }

    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->check(!str_contains($haystack, $needle), $message ?: "Text must not contain «{$needle}»");
    }

    protected function assertMatchesRegularExpression(string $pattern, string $text, string $message = ''): void
    {
        $this->check(preg_match($pattern, $text) === 1, $message ?: "Text does not match {$pattern}");
    }

    protected function assertEmpty(mixed $value, string $message = ''): void
    {
        $this->check(empty($value), $message ?: 'Expected empty, got ' . $this->export($value));
    }

    /** @param class-string<Throwable> $class */
    protected function assertThrows(string $class, callable $callback, string $message = ''): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->check($e instanceof $class, $message ?: "Expected {$class}, got " . $e::class . ': ' . $e->getMessage());

            return $e;
        }
        $this->check(false, $message ?: "Expected exception {$class}");
    }

    protected function check(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    protected function export(mixed $value): string
    {
        $text = is_string($value) ? '"' . $value . '"' : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : $text;
    }
}

final class AssertionFailed extends \RuntimeException
{
}
