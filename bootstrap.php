<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the web front controller and the CLI.
 * Uses Composer's PSR-4 autoloader; falls back to an equivalent loader when
 * `composer install` has not been run yet (e.g. a fresh XAMPP copy).
 */

$composer = __DIR__ . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'App\\')) {
            $file = __DIR__ . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require __DIR__ . '/app/Helpers/functions.php';
}

return new App\Core\Application(__DIR__);
