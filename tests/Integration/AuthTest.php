<?php

declare(strict_types=1);

namespace Tests\Integration;

final class AuthTest extends IntegrationTestCase
{
    public function testWrongPasswordIsRejectedWithoutRevealingTheReason(): void
    {
        $this->user('qasymova.i');
        $http = new Http();
        preg_match('/name="_token" value="([^"]+)"/', $http->get('/login')['body'], $m);
        $http->request('POST', '/login', ['_token' => $m[1], 'login' => 'qasymova.i', 'password' => 'wrong-password'], [], false);
        $page = $http->get('/login')['body'];
        $this->assertStringContainsString('invalid-feedback', $page);
        $this->assertSame('/login', $http->get('/dashboard')['location'], 'Not signed in');
    }

    public function testSignInAndOut(): void
    {
        $http = $this->as('qasymova.i');
        $this->assertSame(200, $http->get('/dashboard')['status']);
        $this->assertSame(405, $http->get('/logout')['status'], 'Logout only by POST');
        $this->assertSame(302, $http->request('POST', '/logout', [])['status']);
        $this->assertSame('/login', $http->get('/dashboard')['location']);
    }

    public function testCsrfTokenIsRequired(): void
    {
        $http = $this->as('qasymova.i');
        $this->assertSame(419, $http->request('POST', '/logout', [], [], false)['status']);
        $this->assertSame(419, $http->request('POST', '/api/notifications/read', [], ['Accept: application/json'], false)['status'], 'API call without the X-CSRF-Token header');
        $this->assertSame(200, $http->json('POST', '/api/notifications/read')['status'], 'API call with the token');
    }

    public function testTemporaryPasswordMustBeChanged(): void
    {
        $this->user('nurlanova.ze');
        $this->db()->prepare("UPDATE users SET must_change_password = 1 WHERE login = 'nurlanova.ze'")->execute();
        $http = (new Http())->login('nurlanova.ze');
        $this->assertSame('/profile/password', $http->get('/dashboard')['location']);
        $this->assertSame('/profile/password', $http->get('/journal')['location']);
        $this->db()->prepare("UPDATE users SET must_change_password = 0 WHERE login = 'nurlanova.ze'")->execute();
    }

    public function testBruteForceIsThrottled(): void
    {
        $this->user('uzaqov.m');
        $http = new Http();
        for ($i = 0; $i < 6; $i++) {
            preg_match('/name="_token" value="([^"]+)"/', $http->get('/login')['body'], $m);
            $http->request('POST', '/login', ['_token' => $m[1], 'login' => 'uzaqov.m', 'password' => 'bad' . $i], [], false);
        }
        preg_match('/name="_token" value="([^"]+)"/', $http->get('/login')['body'], $m);
        $http->request('POST', '/login', ['_token' => $m[1], 'login' => 'uzaqov.m', 'password' => Environment::PASSWORD], [], false);
        $this->assertSame('/login', $http->get('/dashboard')['location'], 'Correct password is refused while the login is throttled');
        $this->db()->prepare('DELETE FROM login_attempts')->execute();
    }

    public function testSecurityHeaders(): void
    {
        $ch = curl_init(Environment::baseUrl() . '/login');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => false]);
        $raw = strtolower((string) curl_exec($ch));
        foreach (['content-security-policy:', "x-frame-options:", 'x-content-type-options: nosniff', 'referrer-policy:'] as $h) {
            $this->assertStringContainsString($h, $raw, "Header {$h}");
        }
        $this->assertMatchesRegularExpression('/set-cookie:[^\n]*httponly/', $raw, 'Session cookie is HttpOnly');
        $this->assertMatchesRegularExpression('/set-cookie:[^\n]*samesite=lax|set-cookie:[^\n]*samesite=strict/', $raw, 'Session cookie has SameSite');
    }
}
