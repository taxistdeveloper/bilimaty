<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Repositories\SettingsRepository;
use Throwable;

/**
 * Read-through cache of the settings table for the current request.
 */
final class Settings
{
    /** @var array<string, string|null>|null */
    private static ?array $values = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$values === null) {
            try {
                self::$values = (new SettingsRepository())->all();
            } catch (Throwable $e) {
                ErrorHandler::report($e); // settings must never break page rendering
                self::$values = [];
            }
        }

        return self::$values[$key] ?? $default;
    }

    public static function flush(): void
    {
        self::$values = null;
    }

    /** College name in the current interface language. */
    public static function collegeName(): string
    {
        return (string) (self::get('college_name_' . locale()) ?? self::get('college_name_ru') ?? '');
    }
}
