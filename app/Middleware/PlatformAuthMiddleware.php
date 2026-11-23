<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Platform\PlatformAuth;
use Closure;

/**
 * Owner's panel: only a signed-in platform administrator passes; a temporary password
 * must be changed before anything else.
 */
final class PlatformAuthMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $admin = PlatformAuth::user();
        if ($admin === null) {
            if ($request->expectsJson()) {
                return Response::json(null, __('auth.login_required'), 401);
            }
            Session::flash('warning', __('auth.login_required'));

            return Response::redirect(url('/login'));
        }
        if ((int) $admin['must_change_password'] === 1 && !in_array($request->path, ['/password', '/logout'], true)) {
            Session::flash('warning', __('platform.change_password_first'));

            return Response::redirect(url('/password'));
        }

        return $next($request);
    }
}
