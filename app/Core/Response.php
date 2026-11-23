<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object. All controllers return an instance of it.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    /**
     * Unified API format: {"success": bool, "message": string, "data": mixed[, "errors": {...}]}.
     *
     * @param array<string, list<string>>|null $errors
     */
    public static function json(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        ?array $errors = null,
    ): self {
        $payload = [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $data ?? new \stdClass(),
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_HEX_TAG
        );

        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            $defaults = [
                'Content-Type' => 'text/html; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'camera=(), microphone=(self), geolocation=()',
                'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; "
                    . "style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; "
                    . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
            ];
            foreach (array_merge($defaults, $this->headers) as $name => $value) {
                header($name . ': ' . $value, true);
            }
            if (!isset($this->headers['Cache-Control'])) {
                header('Cache-Control: no-store, private');
            }
        }

        echo $this->content;
    }
}
