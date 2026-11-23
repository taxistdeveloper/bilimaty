<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Platform\PlatformAuth;
use App\Platform\PlatformService;
use App\Services\LoginThrottle;

/** Sign-in of the platform owner and the password change. */
final class PlatformAuthController extends Controller
{
    public function showLogin(): Response
    {
        if (PlatformAuth::user() !== null) {
            return $this->redirect('/');
        }

        return $this->view('platform/login', ['title' => __('platform.login_title')], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, ['login' => 'required|string|max:64', 'password' => 'required|string|max:255']);
        $throttle = new LoginThrottle();
        $login = (string) $data['login'];
        $minutes = $throttle->lockedForMinutes('platform:' . $login, $request->ip());
        if ($minutes > 0) {
            return $this->fail(__('auth.throttled', ['minutes' => $minutes]), $login);
        }
        $admin = PlatformAuth::attempt($login, (string) $request->input('password'));
        if ($admin === null) {
            $throttle->hit('platform:' . $login, $request->ip(), false);
            (new PlatformService())->audit('auth.login_failed', null, ['login' => $login]);

            return $this->fail(__('auth.failed'), $login);
        }
        $throttle->clear('platform:' . $login, $request->ip());
        PlatformAuth::login($admin);
        (new PlatformService(null, (int) $admin['id']))->audit('auth.login', null, null);

        return $this->redirect('/');
    }

    public function logout(): Response
    {
        PlatformAuth::logout();
        Session::flash('success', __('auth.logged_out'));

        return $this->redirect('/login');
    }

    public function showPassword(): Response
    {
        return $this->view('platform/password', ['title' => __('platform.password_title')], 'layouts/platform');
    }

    public function password(Request $request): Response
    {
        $data = $this->validate($request, [
            'current_password' => 'required|string|max:255',
            'password' => 'required|password|confirmed|max:255',
        ]);
        $admin = PlatformAuth::user() ?? [];
        if (!password_verify((string) $data['current_password'], (string) ($admin['password_hash'] ?? ''))) {
            throw new \App\Core\ValidationException(['current_password' => [__('profile.current_password_invalid')]]);
        }
        PlatformAuth::setPassword((int) $admin['id'], (string) $data['password']);
        Session::regenerate();
        (new PlatformService(null, (int) $admin['id']))->audit('auth.password_changed', null, null);
        Session::flash('success', __('platform.password_changed'));

        return $this->redirect('/');
    }

    private function fail(string $message, string $login): Response
    {
        Session::flash('error', $message);
        Session::flash('old', ['login' => $login]);

        return $this->redirect('/login');
    }
}
