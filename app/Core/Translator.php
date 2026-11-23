<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads app/Lang/{locale}.php (nested arrays, dot keys) and resolves translations
 * with :placeholder replacement. Falls back to the fallback locale, then to the key.
 */
final class Translator
{
    private static string $path = '';
    private static string $locale = 'ru';
    private static string $fallback = 'ru';

    /** @var list<string> */
    private static array $supported = ['kk', 'ru'];

    /** @var array<string, array<string, mixed>> */
    private static array $loaded = [];

    /** @param list<string> $supported */
    public static function init(string $path, string $locale, string $fallback, array $supported): void
    {
        self::$path = rtrim($path, '/');
        self::$supported = $supported;
        self::$fallback = $fallback;
        self::setLocale($locale);
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = self::isSupported($locale) ? $locale : self::$fallback;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @return list<string> */
    public static function supported(): array
    {
        return self::$supported;
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::$supported, true);
    }

    /** @param array<string, scalar|null> $replace */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $line = self::lookup($key, $locale ?? self::$locale)
            ?? self::lookup($key, self::$fallback)
            ?? $key;

        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string) $value, $line);
        }

        return $line;
    }

    private static function lookup(string $key, string $locale): ?string
    {
        if (!isset(self::$loaded[$locale])) {
            $file = self::$path . '/' . $locale . '.php';
            self::$loaded[$locale] = self::isSupported($locale) && is_file($file) ? require $file : [];
        }

        $value = self::$loaded[$locale];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }
}
