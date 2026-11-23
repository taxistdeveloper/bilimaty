<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The college (tenant) of the current request when several colleges share one installation.
 *
 * Subscription states (computed from paid_until and grace_days):
 *   active    — paid, more than warn_days left;
 *   expiring  — paid, warn_days or fewer left (the administrator sees a reminder);
 *   grace     — the term has ended, full access for grace_days more;
 *   readonly  — after the grace period: pages open, nothing can be changed;
 *   suspended — blocked by the owner: no access at all.
 */
final class Tenant
{
    /** @var array<string, mixed>|null */
    private static ?array $college = null;
    private static bool $platform = false;
    private static ?string $rootBase = null;

    public static function enabled(): bool
    {
        return (bool) Config::get('tenancy.enabled', false);
    }

    /** @param array<string, mixed> $college row of the platform `colleges` table */
    public static function set(array $college, string $rootBase): void
    {
        self::$college = $college;
        self::$platform = false;
        self::$rootBase = $rootBase;
    }

    public static function setPlatform(string $rootBase): void
    {
        self::$college = null;
        self::$platform = true;
        self::$rootBase = $rootBase;
    }

    public static function reset(): void
    {
        self::$college = null;
        self::$platform = false;
        self::$rootBase = null;
    }

    /** @return array<string, mixed>|null */
    public static function current(): ?array
    {
        return self::$college;
    }

    public static function code(): ?string
    {
        return self::$college !== null ? (string) self::$college['code'] : null;
    }

    public static function isPlatform(): bool
    {
        return self::$platform;
    }

    /** Base URL of the whole site (without the college code), null in single-college mode. */
    public static function rootBase(): ?string
    {
        return self::$rootBase;
    }

    /** "/<code>" for the current college, "" otherwise (used to build public links such as QR codes). */
    public static function pathPrefix(): string
    {
        return self::$college !== null ? '/' . self::$college['code'] : (self::$platform ? '/platform' : '');
    }

    /** Value bound to the session so that a cookie of one college never works in another. */
    public static function sessionKey(): string
    {
        return self::$platform ? '@platform' : (self::code() ?? '');
    }

    /** Folder for private files of the current college. */
    public static function storage(string $sub): string
    {
        $base = dirname(__DIR__, 2) . '/storage/private';

        return $base . (self::code() !== null ? '/tenants/' . self::code() : '') . '/' . ltrim($sub, '/');
    }

    public static function state(?array $college = null, ?string $today = null): string
    {
        $college ??= self::$college;
        if ($college === null) {
            return 'active';
        }
        if (in_array($college['status'] ?? 'active', ['suspended', 'archived'], true)) {
            return (string) $college['status'];
        }
        $today ??= date('Y-m-d');
        $paidUntil = (string) ($college['paid_until'] ?? '');
        if ($paidUntil === '') {
            return 'readonly';
        }
        if ($today <= $paidUntil) {
            return self::daysLeft($college, $today) <= (int) Config::get('tenancy.warn_days', 14) ? 'expiring' : 'active';
        }
        $graceEnd = date('Y-m-d', strtotime($paidUntil . ' +' . (int) ($college['grace_days'] ?? Config::get('tenancy.grace_days', 7)) . ' days'));

        return $today <= $graceEnd ? 'grace' : 'readonly';
    }

    /** Days until paid_until (negative after it). */
    public static function daysLeft(?array $college = null, ?string $today = null): int
    {
        $college ??= self::$college;
        $paidUntil = (string) ($college['paid_until'] ?? '');
        if ($paidUntil === '') {
            return 0;
        }

        return (int) round((strtotime($paidUntil) - strtotime($today ?? date('Y-m-d'))) / 86400);
    }

    /** Last day of full access (paid_until + grace). */
    public static function graceEnd(?array $college = null): ?string
    {
        $college ??= self::$college;
        $paidUntil = (string) ($college['paid_until'] ?? '');

        return $paidUntil === '' ? null : date('Y-m-d', strtotime($paidUntil . ' +' . (int) ($college['grace_days'] ?? Config::get('tenancy.grace_days', 7)) . ' days'));
    }

    /** Database name for a new college code. */
    public static function databaseFor(string $code): string
    {
        return Config::get('tenancy.database_prefix', 'bilimaty_') . str_replace('-', '_', $code);
    }

    public static function isValidCode(string $code): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,29}$/', $code) && !in_array($code, (array) Config::get('tenancy.reserved', []), true);
    }
}
