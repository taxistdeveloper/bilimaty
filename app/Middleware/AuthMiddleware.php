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
 * Only authenticated users pass. Web requests are redirected to the login page
 * (remembering the intended URL), API requests receive 401 JSON.
 */
final class AuthMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $message = match (true) {
            Auth::wasRevoked() => __('auth.session_revoked'),
            Session::wasExpired() => __('auth.session_expired'),
            default => __('auth.login_required'),
        };

        if ($request->expectsJson()) {
            return Response::json(null, $message, 401);
        }

        if ($request->method === 'GET') {
            Session::set('auth.intended', $request->fullPath());
        }
        Session::flash('warning', $message);

        return Response::redirect(url('/login'));
    }
}
