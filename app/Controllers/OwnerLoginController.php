<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Tenant;
use App\Platform\CollegeManager;
use App\Services\AuditLogger;

/**
 * Several colleges: the platform owner enters the college as one of its administrators through a
 * one-time link from the owner's panel. The session is marked, a banner is shown on every page
 * and the entry is written to the college's audit log.
 */
final class OwnerLoginController extends Controller
{
    public function enter(Request $request): Response
    {
        $college = Tenant::current();
        $grant = $college !== null ? CollegeManager::consume($college, (string) $request->query('token', '')) : null;
        if ($grant === null) {
            Session::flash('error', __('platform.impersonate_expired'));

            return $this->redirect('/login');
        }
        Session::regenerate();
        CSRF::regenerate();
        Session::set('auth', ['user_id' => $grant['user_id'], 'login_at' => time()]);
        Session::set('impersonator', ['name' => $grant['admin']]);
        \App\Core\Auth::reset();
        AuditLogger::log('auth.owner_login', 'users', $grant['user_id'], null, ['owner' => $grant['admin']], $grant['user_id']);

        return $this->redirect('/dashboard');
    }
}
