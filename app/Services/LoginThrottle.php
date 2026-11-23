<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Brute-force protection for the login form, backed by the login_attempts table.
 * Limits failed attempts per (login + IP) and per IP within a sliding window.
 */
final class LoginThrottle
{
    private int $maxPerLogin;
    private int $maxPerIp;
    private int $windowMinutes;

    public function __construct()
    {
        $this->maxPerLogin = (int) Config::get('app.auth.max_attempts', 5);
        $this->maxPerIp = (int) Config::get('app.auth.max_attempts_per_ip', 30);
        $this->windowMinutes = (int) Config::get('app.auth.decay_minutes', 15);
    }

    /** Minutes until the next attempt is allowed, 0 when not locked. */
    public function lockedForMinutes(string $login, string $ip): int
    {
        $login = $this->normalize($login);
        $since = date('Y-m-d H:i:s', time() - $this->windowMinutes * 60);

        $row = Database::fetch(
            'SELECT
                SUM(identifier = ? AND ip_address = ?) AS by_login,
                COUNT(*) AS by_ip,
                MIN(attempted_at) AS oldest
             FROM login_attempts
             WHERE ip_address = ? AND successful = 0 AND attempted_at >= ?',
            [$login, $ip, $ip, $since]
        );

        $byLogin = (int) ($row['by_login'] ?? 0);
        $byIp = (int) ($row['by_ip'] ?? 0);
        if ($byLogin < $this->maxPerLogin && $byIp < $this->maxPerIp) {
            return 0;
        }

        $oldest = strtotime((string) ($row['oldest'] ?? 'now')) ?: time();
        $unlockAt = $oldest + $this->windowMinutes * 60;

        return max(1, (int) ceil(($unlockAt - time()) / 60));
    }

    public function hit(string $login, string $ip, bool $successful): void
    {
        Database::execute(
            'INSERT INTO login_attempts (identifier, ip_address, successful) VALUES (?, ?, ?)',
            [$this->normalize($login), $ip, $successful ? 1 : 0]
        );
    }

    /** Successful login resets the counter for this login + IP. */
    public function clear(string $login, string $ip): void
    {
        Database::execute(
            'DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ? AND successful = 0',
            [$this->normalize($login), $ip]
        );
    }

    private function normalize(string $login): string
    {
        return mb_substr(mb_strtolower(trim($login)), 0, 190);
    }
}
