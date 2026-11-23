<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Builds a unique login "lastname.fi" from a Russian/Kazakh name (Latin transliteration),
 * appending a number when the login is taken: ivanov.ps, ivanov.ps2, ...
 */
final class LoginGenerator
{
    private const MAP = [
        'а' => 'a', 'ә' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'ғ' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'і' => 'i', 'к' => 'k', 'қ' => 'q', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'ң' => 'n', 'о' => 'o', 'ө' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ұ' => 'u', 'ү' => 'u', 'ф' => 'f', 'х' => 'kh', 'һ' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh',
        'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'iu', 'я' => 'ia',
    ];

    public static function transliterate(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $result = strtr($text, self::MAP);

        return (string) preg_replace('/[^a-z0-9]+/', '', $result);
    }

    public static function generate(string $lastName, string $firstName, ?string $middleName = null): string
    {
        $base = self::transliterate($lastName);
        $base = $base !== '' ? substr($base, 0, 40) : 'user';
        $initials = substr(self::transliterate($firstName), 0, 1) . substr(self::transliterate((string) $middleName), 0, 1);
        $login = $initials !== '' ? $base . '.' . $initials : $base;

        $candidate = $login;
        for ($i = 2; self::taken($candidate); $i++) {
            $candidate = $login . $i;
        }

        return $candidate;
    }

    private static function taken(string $login): bool
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM users WHERE login = ?', [$login]) > 0;
    }
}
