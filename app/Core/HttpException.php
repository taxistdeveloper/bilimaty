<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception that maps directly to an HTTP status code (403, 404, 419, ...).
 */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }
}
