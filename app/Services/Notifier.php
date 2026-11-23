<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Repositories\NotificationRepository;

/**
 * In-app notifications. Texts are stored in each recipient's interface language.
 */
final class Notifier
{
    /**
     * @param list<int> $userIds
     * @param array<string, scalar> $replace
     */
    public static function send(array $userIds, string $type, string $titleKey, ?string $bodyKey, array $replace, ?string $link): void
    {
        $repository = new NotificationRepository();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        foreach ($repository->locales($userIds) as $userId => $locale) {
            $repository->create(
                $userId,
                $type,
                Translator::get($titleKey, $replace, $locale),
                $bodyKey !== null ? Translator::get($bodyKey, $replace, $locale) : null,
                $link
            );
        }
    }
}
