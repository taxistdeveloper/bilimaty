<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Route-level RBAC: 'can:students.view' or 'can:journal.view,journal.manage' (any of).
 * Record-level checks (own group, own student card) are done by Policies.
 */
final class PermissionMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if ($params === [] || !Auth::canAny(...$params)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}
