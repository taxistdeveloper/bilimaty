<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lazy loader for config/*.php files with dot-notation access: Config::get('app.name').
 */
final class Config
{
    private static string $path = '';

    /** @var array<string, array<string, mixed>> */
    private static array $items = [];

    public static function init(string $path): void
    {
        self::$path = rtrim($path, '/');
        self::$items = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!array_key_exists($file, self::$items)) {
            $filePath = self::$path . '/' . $file . '.php';
            if (!preg_match('/^[a-z0-9_]+$/', $file) || !is_file($filePath)) {
                return $default;
            }
            self::$items[$file] = require $filePath;
        }

        $value = self::$items[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** Override a value for the current process (e.g. the database of the current college). */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);
        self::get($file . '.__load__'); // make sure the file is loaded first
        $ref = &self::$items[$file];
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = $ref[$segment] ?? [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
