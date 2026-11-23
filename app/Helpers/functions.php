<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Auth;
use App\Core\Config;
use App\Core\CSRF;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Core\Translator;

/** Escape for HTML output (XSS protection). Use in every template. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, scalar|null> $replace */
function __(string $key, array $replace = []): string
{
    return Translator::get($key, $replace);
}

function locale(): string
{
    return Translator::locale();
}

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function base_path(string $path = ''): string
{
    return Application::instance()->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

/** Absolute-path URL that respects installs in a sub-folder. */
function url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = Application::instance()->request()?->baseUrl ?? '';

    return $base . '/' . ltrim($path, '/');
}

/** @param array<string, scalar> $params */
function route(string $name, array $params = []): string
{
    return url(Application::instance()->router()->path($name, $params));
}

/** Public asset URL with cache-busting by modification time. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = base_path('public/' . $path);
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    // Several colleges: static files live at the site root, not under /<code>.
    $root = App\Core\Tenant::rootBase();

    return ($root !== null ? $root . '/' . $path : url($path)) . $version;
}

function csrf_token(): string
{
    return CSRF::token();
}

function csrf_field(): string
{
    return CSRF::field();
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

/** Previously submitted value after a failed validation. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('old', []);

    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

/** First validation error for a field, or null. */
function error(string $field): ?string
{
    $errors = Session::getFlash('errors', []);

    return is_array($errors) && isset($errors[$field][0]) ? (string) $errors[$field][0] : null;
}

/** @return array<string, list<string>> */
function errors(): array
{
    $errors = Session::getFlash('errors', []);

    return is_array($errors) ? $errors : [];
}

/** @return array<string, mixed>|null */
function auth_user(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

/** @param array<string, mixed> $user */
function full_name(array $user, bool $short = false): string
{
    $last = trim((string) ($user['last_name'] ?? ''));
    $first = trim((string) ($user['first_name'] ?? ''));
    $middle = trim((string) ($user['middle_name'] ?? ''));

    if ($short) {
        $initials = ($first !== '' ? mb_substr($first, 0, 1) . '.' : '')
            . ($middle !== '' ? ' ' . mb_substr($middle, 0, 1) . '.' : '');

        return trim($last . ' ' . $initials);
    }

    return trim(implode(' ', array_filter([$last, $first, $middle])));
}

/** Localized column of a dictionary row: name_ru / name_kk. */
function localized(array $row, string $field = 'name'): string
{
    return (string) ($row[$field . '_' . locale()] ?? $row[$field . '_ru'] ?? $row[$field] ?? '');
}

function format_date(?string $value, string $format = 'd.m.Y'): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Exception) {
        return '—';
    }
}

/** Same-origin referer only (prevents open redirects). */
function safe_referer(Request $request): ?string
{
    $referer = $request->referer();
    if ($referer === null) {
        return null;
    }
    $parts = parse_url($referer);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!is_array($parts) || ($parts['host'] ?? '') === '') {
        return null;
    }
    $refererHost = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (!hash_equals((string) $host, $refererHost)) {
        return null;
    }

    return ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

/** Internal path check for "intended URL" redirects. */
function is_safe_internal_path(string $path): bool
{
    return str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\');
}
