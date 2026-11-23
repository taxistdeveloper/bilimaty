<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Notifications of a single user. Every query is scoped by user_id,
 * so one user can never read another user's notifications.
 */
final class NotificationRepository
{
    /** @return list<array<string, mixed>> */
    public function latestFor(int $userId, int $limit = 10): array
    {
        return Database::fetchAll(
            'SELECT id, type, title, body, link, read_at, created_at
             FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)),
            [$userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function markAllRead(int $userId): int
    {
        return Database::execute(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function create(int $userId, string $type, string $title, ?string $body, ?string $link): void
    {
        Database::execute(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
            [$userId, mb_substr($type, 0, 50), mb_substr($title, 0, 255), $body, $link !== null ? mb_substr($link, 0, 255) : null]
        );
    }

    /**
     * Interface language of each user (to write a notification in the recipient's language).
     *
     * @param list<int> $userIds
     * @return array<int, string>
     */
    public function locales(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $out = [];
        foreach (Database::fetchAll('SELECT id, locale FROM users WHERE id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')', $userIds) as $row) {
            $out[(int) $row['id']] = (string) $row['locale'];
        }

        return $out;
    }
}
