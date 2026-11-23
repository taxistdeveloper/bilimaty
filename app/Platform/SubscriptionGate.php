<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;

/**
 * Access of a college by its subscription:
 *   suspended → nothing opens (a page with the reason);
 *   readonly  → pages open, changes are refused (sign-in, sign-out, language and the forced
 *               password change still work).
 */
final class SubscriptionGate
{
    private const ALLOWED_WRITES = ['/login', '/logout', '/profile/password'];

    public static function check(Request $request): ?Response
    {
        $state = Tenant::state();
        if ($state === 'suspended' || $state === 'archived') {
            if ($request->expectsJson()) {
                return Response::json(null, __('platform.blocked_title'), 403);
            }
            $html = Application::instance()->view()->render('platform/blocked', ['college' => Tenant::current()], null);

            return new Response($html, 403);
        }
        if ($state !== 'readonly' || in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }
        if (in_array($request->path, self::ALLOWED_WRITES, true) || str_starts_with($request->path, '/lang/')) {
            return null;
        }
        if ($request->expectsJson()) {
            return Response::json(null, __('platform.readonly_message'), 402);
        }
        Session::flash('error', __('platform.readonly_message'));

        return Response::redirect(safe_referer($request) ?? url('/dashboard'));
    }
}
