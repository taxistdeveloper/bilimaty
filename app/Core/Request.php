<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish wrapper around the current HTTP request.
 */
final class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $baseUrl,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $files = [],
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = rawurldecode(is_string($uri) && $uri !== '' ? $uri : '/');
        [$baseUrl, $path] = self::resolveBase($uri, (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        $body = $_POST;
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        // HTML forms can only send GET/POST: allow _method spoofing for PUT/PATCH/DELETE.
        if ($method === 'POST' && isset($body['_method']) && is_string($body['_method'])) {
            $spoofed = strtoupper($body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoofed;
            }
        }

        return new self($method, $path, $baseUrl, $_GET, $body, $_SERVER, $_FILES);
    }

    /**
     * Supports document root = public/ (OpenServer) and project-root installs
     * where the root .htaccess rewrites into public/ (XAMPP subfolder).
     *
     * @return array{0: string, 1: string}
     */
    private static function resolveBase(string $uri, string $scriptName): array
    {
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $scriptDir = in_array($scriptDir, ['/', '.', ''], true) ? '' : rtrim($scriptDir, '/');

        $candidates = [$scriptDir];
        if (str_ends_with($scriptDir, '/public')) {
            $candidates[] = substr($scriptDir, 0, -strlen('/public'));
        }

        foreach ($candidates as $base) {
            if ($base === '') {
                return ['', self::normalizePath($uri)];
            }
            if ($uri === $base || str_starts_with($uri, $base . '/')) {
                return [$base, self::normalizePath(substr($uri, strlen($base)))];
            }
        }

        return ['', self::normalizePath($uri)];
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim(preg_replace('#/+#', '/', $path) ?? '', '/');

        return $path === '' ? '/' : $path;
    }

    /** The same request seen from a sub-application (e.g. a college at <base>/<code>). */
    public function withBase(string $baseUrl, string $path): self
    {
        return new self($this->method, self::normalizePath($path), $baseUrl, $this->query, $this->body, $this->server, $this->files);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();

        return array_intersect_key($all, array_flip($keys));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function expectsJson(): bool
    {
        return $this->isApi()
            || str_contains((string) $this->header('Accept'), 'application/json')
            || $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return (!empty($https) && $https !== 'off')
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443;
    }

    public function ip(): string
    {
        // Proxy headers are not trusted by default: they can be forged by the client.
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function referer(): ?string
    {
        $referer = $this->server['HTTP_REFERER'] ?? null;

        return is_string($referer) ? $referer : null;
    }

    public function fullPath(): string
    {
        $qs = (string) ($this->server['QUERY_STRING'] ?? '');

        return $this->path . ($qs !== '' ? '?' . $qs : '');
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }
}
