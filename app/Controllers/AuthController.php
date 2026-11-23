<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Repositories\UserRepository;
use App\Services\AuditLogger;
use App\Services\LoginThrottle;

final class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return $this->view('auth/login', ['title' => __('auth.title')], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'login' => 'required|string|max:190',
            'password' => 'required|string|max:255',
        ]);

        $login = (string) $data['login'];
        $ip = $request->ip();
        $throttle = new LoginThrottle();

        $minutes = $throttle->lockedForMinutes($login, $ip);
        if ($minutes > 0) {
            AuditLogger::log('auth.login_locked', null, null, null, ['login' => $login]);

            return $this->failed($request, __('auth.throttled', ['minutes' => $minutes]), $login);
        }

        $user = Auth::attempt($login, (string) $request->input('password'));
        if ($user === null) {
            $throttle->hit($login, $ip, false);
            AuditLogger::log('auth.login_failed', null, null, null, ['login' => $login]);

            return $this->failed($request, __('auth.failed'), $login);
        }

        $throttle->clear($login, $ip);
        $throttle->hit($login, $ip, true);
        Auth::login($user, $ip);
        // A language chosen on the login page is kept and saved to the profile.
        $chosen = Session::get('locale');
        if (is_string($chosen) && Translator::isSupported($chosen)) {
            (new UserRepository())->updateLocale((int) $user['id'], $chosen);
        }
        AuditLogger::log('auth.login', 'users', (int) $user['id'], null, null, (int) $user['id']);

        $intended = Session::get('auth.intended');
        Session::remove('auth.intended');
        $target = is_string($intended) && is_safe_internal_path($intended) ? $intended : '/dashboard';

        return $this->redirect($target);
    }

    public function logout(): Response
    {
        $userId = Auth::id();
        if ($userId !== null) {
            AuditLogger::log('auth.logout', 'users', $userId, null, null, $userId);
        }
        Auth::logout();
        Session::flash('success', __('auth.logged_out'));

        return $this->redirect('/login');
    }

    private function failed(Request $request, string $message, string $login): Response
    {
        Session::flash('errors', ['login' => [$message]]);
        Session::flash('old', ['login' => $login]);

        return $this->redirect('/login');
    }
}
