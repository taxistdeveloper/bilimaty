<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\UserRepository;
use App\Services\AuditLogger;

/**
 * Personal cabinet of the signed-in user. Works only with Auth::id(),
 * never with an id from the URL, so nobody can open someone else's profile.
 */
final class ProfileController extends Controller
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function show(): Response
    {
        $id = (int) Auth::id();

        return $this->view('profile/show', [
            'title' => __('profile.title'),
            'user' => $this->users->find($id),
            'roles' => $this->users->roles($id),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) Auth::id();
        $data = $this->validate($request, [
            'email' => "nullable|email|max:190|unique:users,email,{$id}",
            'phone' => 'nullable|phone|max:20',
            'locale' => 'required|in:kk,ru',
        ]);

        $before = $this->users->find($id) ?? [];
        $this->users->update($id, $data);
        Session::set('locale', $data['locale']);

        $old = array_intersect_key($before, $data);
        $changedKeys = array_flip(array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', $old))));
        if ($changedKeys !== []) {
            AuditLogger::log('profile.updated', 'users', $id, array_intersect_key($old, $changedKeys), array_intersect_key($data, $changedKeys));
        }
        Session::flash('success', __('profile.saved'));

        return $this->redirect('/profile');
    }

    public function password(): Response
    {
        return $this->view('profile/password', [
            'title' => __('profile.change_password'),
            'forced' => Auth::mustChangePassword(),
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $this->validate($request, [
            'current_password' => 'required|string|max:255',
            'password' => 'required|string|max:255|password|confirmed',
        ]);

        $id = (int) Auth::id();
        $current = (string) $request->input('current_password');
        $new = (string) $request->input('password');
        $hash = (string) $this->users->passwordHash($id);

        if (!password_verify($current, $hash)) {
            AuditLogger::log('profile.password_change_failed', 'users', $id);
            throw new ValidationException(['current_password' => [__('profile.current_password_invalid')]]);
        }
        if (password_verify($new, $hash)) {
            throw new ValidationException(['password' => [__('profile.password_same')]]);
        }

        $wasForced = Auth::mustChangePassword();
        $this->users->setPassword($id, password_hash($new, PASSWORD_DEFAULT), false);
        Auth::refreshSession(); // this session stays, all other sessions of the user end
        AuditLogger::log('profile.password_changed', 'users', $id);
        Session::flash('success', __('profile.password_changed'));

        return $this->redirect($wasForced ? '/dashboard' : '/profile');
    }
}
