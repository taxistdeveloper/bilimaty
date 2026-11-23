<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/** Pages for anonymous users only (login). Authenticated users go to the dashboard. */
final class GuestMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (Auth::check()) {
            return Response::redirect(url('/dashboard'));
        }

        return $next($request);
    }
}
