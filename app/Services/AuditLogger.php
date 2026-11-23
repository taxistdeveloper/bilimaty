<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\Auth;
use App\Core\Database;
use App\Core\ErrorHandler;
use Throwable;

/**
 * Writes the security / business audit trail (who, what, when, from where).
 * Failures are logged but never break the user's action.
 */
final class AuditLogger
{
    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): void {
        $request = Application::instance()->request();
        try {
            Database::execute(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId ?? Auth::id(),
                    $action,
                    $entityType,
                    $entityId,
                    $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
                    $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
                    $request?->ip(),
                    $request?->userAgent(),
                ]
            );
        } catch (Throwable $e) {
            ErrorHandler::report($e);
        }
    }
}
