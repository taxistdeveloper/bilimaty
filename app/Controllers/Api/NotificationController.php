<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Repositories\NotificationRepository;

/** Notifications of the current user only (scoped by session user id, never by URL). */
final class NotificationController extends Controller
{
    public function index(): Response
    {
        $repository = new NotificationRepository();
        $userId = (int) Auth::id();

        return $this->json([
            'unread' => $repository->unreadCount($userId),
            'items' => $repository->latestFor($userId),
        ]);
    }

    public function markAllRead(): Response
    {
        $updated = (new NotificationRepository())->markAllRead((int) Auth::id());

        return $this->json(['updated' => $updated], __('notifications.marked_read'));
    }
}
