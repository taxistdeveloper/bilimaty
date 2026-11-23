<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader (KEY=VALUE per line, # comments, optional quotes).
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                continue;
            }

            $quoted = strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[-1] === $value[0];
            if ($quoted) {
                $value = substr($value, 1, -1);
            } elseif (($pos = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $pos));
            }

            // Real environment variables take precedence over the file.
            $real = getenv($key);
            self::$values[$key] = $real !== false ? $real : $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values[$key] ?? (getenv($key) !== false ? getenv($key) : null);
        if ($value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
