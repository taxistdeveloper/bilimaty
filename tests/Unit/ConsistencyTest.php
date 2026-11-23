<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Application;
use App\Core\Config;
use Tests\TestCase;

/**
 * Configuration that must agree: permissions, roles, menu, routes and both interface languages.
 */
final class ConsistencyTest extends TestCase
{
    /** @return list<string> */
    private function flatten(array $a, string $prefix = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $out = array_merge($out, is_array($v) ? $this->flatten($v, $prefix . $k . '.') : [$prefix . $k]);
        }

        return $out;
    }

    public function testRussianAndKazakhHaveTheSameKeysAndPlaceholders(): void
    {
        $root = dirname(__DIR__, 2);
        $ru = require $root . '/app/Lang/ru.php';
        $kk = require $root . '/app/Lang/kk.php';
        $this->assertSame([], array_values(array_diff($this->flatten($ru), $this->flatten($kk))), 'Keys missing in kk');
        $this->assertSame([], array_values(array_diff($this->flatten($kk), $this->flatten($ru))), 'Keys missing in ru');
        $get = static function (array $a, string $key) {
            foreach (explode('.', $key) as $k) {
                $a = $a[$k];
            }

            return (string) $a;
        };
        foreach ($this->flatten($ru) as $key) {
            preg_match_all('/:([a-z_]+)/', $get($ru, $key), $a);
            preg_match_all('/:([a-z_]+)/', $get($kk, $key), $b);
            sort($a[1]);
            sort($b[1]);
            $this->assertSame(array_values(array_unique($a[1])), array_values(array_unique($b[1])), "Placeholders differ in {$key}");
        }
    }

    public function testTranslationKeysUsedInCodeExist(): void
    {
        $root = dirname(__DIR__, 2);
        $ru = require $root . '/app/Lang/ru.php';
        $missing = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app'));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Lang/')) {
                continue;
            }
            preg_match_all("/__\\('([a-z_]+\\.[a-zA-Z0-9_.]+)'(?:\\)|,)/", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                $v = $ru;
                foreach (explode('.', $key) as $k) {
                    if (!is_array($v) || !array_key_exists($k, $v)) {
                        $missing[$key] = true;
                        break;
                    }
                    $v = $v[$k];
                }
            }
        }
        $this->assertSame([], array_keys($missing), 'Missing translations');
    }

    public function testRolesMenuAndRoutesUseKnownPermissions(): void
    {
        $permissions = array_keys((array) Config::get('permissions.permissions'));
        foreach ((array) Config::get('permissions.roles') as $slug => [, , $list]) {
            $this->assertSame([], array_values(array_diff($list, $permissions)), "Role {$slug} has unknown permissions");
        }
        foreach ((array) Config::get('navigation') as $item) {
            foreach ($item['can'] ?? [] as $p) {
                $this->assertContains($p, $permissions, "Menu item {$item['label']}");
            }
        }
        foreach (Application::instance()->routes() as $route) {
            foreach ($route['middleware'] as $mw) {
                if (str_starts_with($mw, 'can:')) {
                    foreach (explode(',', substr($mw, 4)) as $p) {
                        $this->assertContains($p, $permissions, "Route {$route['uri']}");
                    }
                }
            }
        }
    }

    /**
     * Independent of the route definitions themselves: any route that is not explicitly public or
     * personal must check a permission. Removing a "can:" from a route makes this test fail.
     */
    public function testEveryRouteIsPublicPersonalOrPermissionChecked(): void
    {
        $public = ['GET /', 'GET /lang/{locale:[a-z]{2}}', 'GET /verify', 'GET /verify/{code:[A-Fa-f0-9]{32}}', 'GET /login', 'POST /login',
            'GET /owner-login']; // one-time token from the owner's panel (several colleges)
        $personal = ['POST /logout', 'GET /profile', 'PUT /profile', 'GET /profile/password', 'PUT /profile/password', 'GET /api/notifications', 'POST /api/notifications/read',
            // Demo "view as role": checked in DemoRoleSwitch (super admin, never in production).
            'POST /demo/role', 'POST /demo/back'];
        foreach (Application::instance()->routes() as $route) {
            $key = $route['method'] . ' ' . $route['uri'];
            if (!in_array('auth', $route['middleware'], true)) {
                $this->assertContains($key, $public, "{$key} is open without sign-in");
                continue;
            }
            $checked = array_filter($route['middleware'], static fn (string $m): bool => str_starts_with($m, 'can:') || str_starts_with($m, 'role:')) !== [];
            $this->assertTrue($checked || in_array($key, $personal, true), "{$key} has no permission check");
        }
    }

    public function testStateChangingRoutesAreNotGet(): void
    {
        foreach (Application::instance()->routes() as $route) {
            if ($route['method'] === 'GET') {
                $this->assertSame(0, preg_match('#/(delete|destroy|approve|revoke|logout|publish|close)$#', $route['uri']), "GET must not change state: {$route['uri']}");
            }
        }
    }
}
