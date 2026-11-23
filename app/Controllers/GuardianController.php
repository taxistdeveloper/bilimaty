<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Repositories\StudentRepository;
use App\Services\AccessScope;
use App\Services\GuardianService;

/**
 * Parents on the student card: create a parent account, link an existing one, unlink.
 * Requires students.manage within the user's scope.
 */
final class GuardianController extends Controller
{
    public function store(Request $request, int $id): Response
    {
        $student = $this->manageable($id);
        $service = new GuardianService();
        $relationRule = 'required|in:' . implode(',', GuardianService::RELATIONS);
        try {
            if ($request->input('mode') === 'link') {
                $data = $this->validate($request, ['parent_login' => 'required|string|max:64', 'relation' => $relationRule]);
                $service->linkExisting($student, (string) $data['parent_login'], (string) $data['relation']);
                Session::flash('success', __('guardians.linked'));
            } else {
                $data = $this->validate($request, [
                    'last_name' => 'required|string|max:100',
                    'first_name' => 'required|string|max:100',
                    'middle_name' => 'nullable|string|max:100',
                    'phone' => 'nullable|phone',
                    'email' => 'nullable|email|max:190|unique:users,email',
                    'relation' => $relationRule,
                    'locale' => 'required|in:kk,ru',
                ]);
                $result = $service->createAndLink($student, $data);
                Session::flash('temp_password', ['login' => $result['login'], 'password' => $result['password']]);
                Session::flash('success', __('guardians.created'));
            }
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all() + ['_guardian_form' => (string) $request->input('mode', 'new')]);
        }

        return $this->redirect('/students/' . $id . '#guardians');
    }

    public function destroy(int $id, int $userId): Response
    {
        $student = $this->manageable($id);
        (new GuardianService())->unlink($student, $userId);
        Session::flash('success', __('guardians.unlinked'));

        return $this->redirect('/students/' . $id . '#guardians');
    }

    /** @return array<string, mixed> */
    private function manageable(int $id): array
    {
        $student = (new StudentRepository())->find($id) ?? throw new HttpException(404);
        $groupId = $student['current_group_id'] !== null ? (int) $student['current_group_id'] : null;
        if (!Auth::can('students.manage') || !AccessScope::allowsGroup($groupId)) {
            throw new HttpException(403);
        }

        return $student;
    }
}
