<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown by Controller::validate(); rendered as 422 JSON or redirect-back with errors.
 */
final class ValidationException extends HttpException
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $oldInput
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $oldInput = [],
    ) {
        parent::__construct(422, 'validation.failed');
    }
}
