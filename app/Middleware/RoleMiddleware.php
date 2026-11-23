<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/** Route restricted to one of the listed roles: 'role:super_admin,admin'. */
final class RoleMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (!Auth::hasRole(Auth::SUPER_ADMIN, ...$params)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
