<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\CSRF;
use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use Closure;

/** Verifies the CSRF token on every state-changing request (forms and fetch). */
final class CsrfMiddleware implements Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (!in_array($request->method, self::SAFE_METHODS, true)) {
            $token = $request->input('_token') ?? $request->header('X-CSRF-Token');
            if (!CSRF::verify(is_string($token) ? $token : null)) {
                throw new HttpException(419);
            }
        }

        return $next($request);
    }
}
