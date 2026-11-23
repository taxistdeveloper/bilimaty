<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Users with an administrator-issued password must set their own one first.
 * Only the password page and logout stay reachable until then.
 */
final class PasswordChangeMiddleware implements Middleware
{
    private const ALLOWED = ['/profile/password', '/logout'];

    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (!Auth::check() || !Auth::mustChangePassword() || in_array($request->path, self::ALLOWED, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::json(null, __('profile.must_change_password'), 403);
        }

        Session::flash('warning', __('profile.must_change_password'));

        return Response::redirect(url('/profile/password'));
    }
}
