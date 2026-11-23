<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Random temporary passwords that satisfy the password policy
 * (no look-alike characters: 0/O, 1/l/I).
 */
final class PasswordGenerator
{
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    private const DIGITS = '23456789';

    public static function generate(int $length = 14): string
    {
        $alphabet = self::LETTERS . self::DIGITS;
        do {
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (!preg_match('/\d/', $password) || !preg_match('/[A-Za-z]/', $password));

        return $password;
    }
}
