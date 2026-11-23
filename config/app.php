<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PasswordChangeMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RoleMiddleware;

return [
    'name' => env('APP_NAME', 'BILIMATY'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Almaty'),

    'locale' => env('APP_LOCALE', 'ru'),
    'fallback_locale' => 'ru',
    'locales' => ['kk', 'ru'],

    'session' => [
        'name' => env('SESSION_NAME', 'collegeos_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 30), // idle minutes
        'secure' => (bool) env('SESSION_SECURE_COOKIE', false), // forced on automatically under HTTPS
        'save_path' => env('SESSION_SAVE_PATH') ?: null,
    ],

    'auth' => [
        'max_attempts' => (int) env('AUTH_MAX_ATTEMPTS', 5),
        'max_attempts_per_ip' => (int) env('AUTH_MAX_ATTEMPTS_PER_IP', 30),
        'decay_minutes' => (int) env('AUTH_DECAY_MINUTES', 15),
    ],

    'middleware_aliases' => [
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'csrf' => CsrfMiddleware::class,
        'can' => PermissionMiddleware::class,
        'role' => RoleMiddleware::class,
        'password.fresh' => PasswordChangeMiddleware::class,
        'platform' => App\Middleware\PlatformAuthMiddleware::class,
    ],

    // Applied to every route.
    'global_middleware' => ['csrf'],
];
