<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Database;
use App\Core\Tenant;
use App\Core\ValidationException;

/**
 * Limits of the college's tariff (several colleges). Without a tariff or in single-college mode nothing is limited.
 */
final class Limits
{
    public static function maxStudents(): ?int
    {
        $max = Tenant::current()['max_students'] ?? null;

        return $max !== null ? (int) $max : null;
    }

    public static function activeStudents(): int
    {
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM students WHERE status = 'active'");
    }

    /** @throws ValidationException when the tariff's limit of active students is reached */
    public static function assertCanAddStudent(): void
    {
        $max = self::maxStudents();
        if ($max !== null && self::activeStudents() >= $max) {
            throw new ValidationException(['last_name' => [__('platform.limit_students', ['max' => $max, 'plan' => (string) (Tenant::current()['plan_name'] ?? '')])]]);
        }
    }
}
