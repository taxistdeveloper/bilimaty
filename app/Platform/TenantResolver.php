<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * Several colleges: picks the college from the first segment of the path and points the
 * database at it.
 *
 *   <base>/                → page with the college code field
 *   <base>/platform/…      → owner's panel (platform database)
 *   <base>/<code>/…        → the college <code> (its own database)
 */
final class TenantResolver
{
    public static function resolve(Request $request): Request|Response
    {
        $root = $request->baseUrl;
        $path = $request->path;
        if ($path === '/') {
            return self::landing($request);
        }
        $parts = explode('/', ltrim($path, '/'), 2);
        $segment = strtolower($parts[0]);
        $rest = '/' . ($parts[1] ?? '');

        if ($segment === 'platform') {
            Config::set('database.connections.mysql.database', Platform::database());
            Database::setConnection(Platform::pdo());
            Tenant::setPlatform($root);

            return $request->withBase($root . '/platform', $rest);
        }

        $college = Tenant::isValidCode($segment) ? Platform::collegeByCode($segment) : null;
        if ($college === null) {
            Tenant::setPlatform($root); // static files from the root; no college data is touched
            throw new HttpException(404);
        }
        Config::set('database.connections.mysql.database', (string) $college['db_name']);
        Database::setConnection(null);
        Tenant::set($college, $root);

        return $request->withBase($root . '/' . $college['code'], $rest);
    }

    /** Root page: enter the college code → /<code>/login. */
    private static function landing(Request $request): Response
    {
        Tenant::setPlatform($request->baseUrl);
        $code = strtolower(trim((string) $request->query('code', '')));
        $error = null;
        if ($code !== '') {
            if (Tenant::isValidCode($code) && Platform::collegeByCode($code) !== null) {
                return Response::redirect($request->baseUrl . '/' . $code . '/login');
            }
            $error = __('platform.landing_unknown');
        }
        $html = Application::instance()->view()->render('platform/landing', ['code' => $code, 'error' => $error, 'base' => $request->baseUrl], null);

        return new Response($html, $error !== null ? 404 : 200);
    }
}
