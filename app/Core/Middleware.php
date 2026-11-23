<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Contract for route middleware. Parameters come from the route definition,
 * e.g. 'can:dashboard.view' passes "dashboard.view" as the first parameter.
 */
interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string ...$params): Response;
}
