<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * HTTP client for integration tests: cookie jar, CSRF token, form posts that follow one redirect.
 */
final class Http
{
    private string $jar;
    private string $csrf = '';

    private string $base;

    /**
     * @param string|null $base another server (e.g. the multi-college one); default — the test server
     * @param string $prefix path of a sub-application ("/<college code>", "/platform") added to relative paths
     */
    public function __construct(?string $base = null, private readonly string $prefix = '')
    {
        $this->jar = (string) tempnam(sys_get_temp_dir(), 'bilimaty_jar_');
        $this->base = $base ?? Environment::baseUrl();
    }

    private function path(string $path): string
    {
        if ($this->prefix === '' || $path === $this->prefix || str_starts_with($path, $this->prefix . '/')) {
            return $path;
        }

        return $this->prefix . $path;
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status: int, body: string, location: string, type: string} */
    public function request(string $method, string $path, ?array $form = null, array $headers = [], bool $withToken = true): array
    {
        $path = $this->path($path);
        $ch = curl_init($this->base . $path);
        $h = array_merge(['Accept: text/html'], $headers);
        if ($form !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(($withToken ? ['_token' => $this->csrf] : []) + $form));
            $h[] = 'Referer: ' . $this->base . $path;
        }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 30]);
        $body = (string) curl_exec($ch);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);

        return [
            'status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => $body,
            'location' => $location !== '' ? (string) parse_url($location, PHP_URL_PATH) : '',
            'redirect' => $location,
            'type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        ];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** JSON API call with the CSRF header. */
    public function json(string $method, string $path, array $data = []): array
    {
        $path = $this->path($path);
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'X-CSRF-Token: ' . $this->csrf],
            CURLOPT_POSTFIELDS => $method === 'GET' ? null : json_encode($data)]);
        $body = (string) curl_exec($ch);

        return ['status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'data' => json_decode($body, true), 'body' => $body];
    }

    /**
     * POST a form and follow the redirect: returns the final page and the validation messages.
     *
     * @return array{status: int, location: string, body: string, errors: list<string>}
     */
    public function submit(string $path, array $form): array
    {
        $r = $this->request('POST', $path, $form);
        $body = '';
        if ($r['location'] !== '') {
            $body = $this->get($r['location'])['body'];
        }
        preg_match_all('/invalid-feedback d-block"[^>]*>\s*([^<]+)/u', $body, $m);

        return ['status' => $r['status'], 'location' => $r['location'], 'body' => $body, 'errors' => array_map('trim', $m[1])];
    }

    /** Multipart upload with the CSRF token; follows one redirect like submit(). */
    public function upload(string $path, array $fields, string $field, string $file, string $name): array
    {
        $path = $this->path($path);
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Referer: ' . $this->base . $path],
            CURLOPT_POSTFIELDS => ['_token' => $this->csrf] + $fields + [$field => new \CURLFile($file, 'application/octet-stream', $name)]]);
        curl_exec($ch);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $path = $location !== '' ? (string) parse_url($location, PHP_URL_PATH) : '';

        return ['status' => $status, 'location' => $path, 'body' => $path !== '' ? $this->get($path)['body'] : ''];
    }

    public function login(string $login, string $password = Environment::PASSWORD): self
    {
        preg_match('/name="_token" value="([^"]+)"/', $this->get('/login')['body'], $m);
        $this->csrf = $m[1] ?? '';
        $this->request('POST', '/login', ['login' => $login, 'password' => $password]);
        $this->refreshToken();

        return $this;
    }

    public function refreshToken(): void
    {
        preg_match('/name="csrf-token" content="([^"]+)"/', $this->get('/profile/password')['body'], $m);
        $this->csrf = $m[1] ?? $this->csrf;
    }

    /** Value of a cookie from the jar (e.g. the session id). */
    public function cookie(string $name): ?string
    {
        foreach (file($this->jar) ?: [] as $line) {
            $cols = explode("\t", trim($line));
            if (count($cols) === 7 && $cols[5] === $name) {
                return $cols[6];
            }
        }

        return null;
    }
}
