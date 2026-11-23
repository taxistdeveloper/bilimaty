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
use App\Policies\UserPolicy;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;

final class UserController extends Controller
{
    private UserRepository $users;
    private RoleRepository $roles;
    private UserPolicy $policy;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->roles = new RoleRepository();
        $this->policy = new UserPolicy($this->roles);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'role' => ctype_digit((string) $request->query('role', '')) ? (int) $request->query('role') : null,
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? (string) $request->query('status') : '',
        ];
        $page = max(1, (int) $request->query('page', 1));

        return $this->view('users/index', [
            'title' => __('users.title'),
            'result' => $this->users->paginate($filters, $page),
            'filters' => $filters,
            'roles' => $this->roles->all(),
            'tempPassword' => Session::pullFlash('temp_password'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('users.manage');

        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $this->authorize('users.manage');
        $data = $this->validate($request, $this->rules(null) + [
            'password' => 'nullable|string|max:255|password',
        ]);

        $result = $this->withOldInput($request, fn (): array => (new UserService())->create($data, $this->requestedRoles($request)));

        Session::flash('success', __('users.created'));
        if ($result['password'] !== null) {
            Session::flash('temp_password', ['login' => $data['login'], 'password' => $result['password']]);
        }

        return $this->redirect('/users');
    }

    public function edit(int $id): Response
    {
        $user = $this->findOr404($id);
        if (!$this->policy->manage($this->users->roleSlugs($id))) {
            throw new HttpException(403);
        }

        return $this->form($user);
    }

    public function update(Request $request, int $id): Response
    {
        $user = $this->findOr404($id);
        $slugs = $this->users->roleSlugs($id);
        if (!$this->policy->manage($slugs)) {
            throw new HttpException(403);
        }

        $data = $this->validate($request, $this->rules($id));
        $roleIds = $this->policy->changeRoles($id, $slugs) ? $this->requestedRoles($request) : null;
        $this->withOldInput($request, fn () => (new UserService())->update($user, $data, $roleIds));

        if ($id === Auth::id() && isset($data['locale'])) {
            Session::set('locale', $data['locale']);
        }
        Session::flash('success', __('users.updated'));

        return $this->redirect('/users');
    }

    public function toggleActive(int $id): Response
    {
        $user = $this->findOr404($id);
        if (!$this->policy->changeStatus($id, $this->users->roleSlugs($id))) {
            throw new HttpException(403);
        }

        $activate = (int) $user['is_active'] === 0;
        (new UserService())->setActive($id, $activate);
        Session::flash('success', __($activate ? 'users.activated' : 'users.deactivated'));

        return $this->redirect('/users');
    }

    public function resetPassword(int $id): Response
    {
        $user = $this->findOr404($id);
        if (!$this->policy->resetPassword($id, $this->users->roleSlugs($id))) {
            throw new HttpException(403);
        }

        $password = (new UserService())->resetPassword($id);
        Session::flash('success', __('users.password_reset_done'));
        Session::flash('temp_password', ['login' => $user['login'], 'password' => $password]);

        return $this->redirect('/users');
    }

    /** @param array<string, mixed>|null $user */
    private function form(?array $user): Response
    {
        $id = $user !== null ? (int) $user['id'] : null;
        $slugs = $id !== null ? $this->users->roleSlugs($id) : [];

        return $this->view('users/form', [
            'title' => $user === null ? __('users.create') : __('users.edit'),
            'user' => $user,
            'roles' => $this->roles->all(),
            'userRoleIds' => $id !== null ? $this->users->roleIds($id) : [],
            'assignable' => $this->policy->assignableRoleIds(),
            'canChangeRoles' => $id === null || $this->policy->changeRoles($id, $slugs),
            'canChangeStatus' => $id !== null && $this->policy->changeStatus($id, $slugs),
            'canResetPassword' => $id !== null && $this->policy->resetPassword($id, $slugs),
        ]);
    }

    /** @return array<string, string> */
    private function rules(?int $ignoreId): array
    {
        $ignore = $ignoreId !== null ? ",{$ignoreId}" : '';

        return [
            'login' => "required|alpha_dash|min:3|max:64|unique:users,login{$ignore}",
            'email' => "nullable|email|max:190|unique:users,email{$ignore}",
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'phone' => 'nullable|phone|max:20',
            'locale' => 'required|in:kk,ru',
            'roles' => 'nullable|array|exists:roles,id',
        ];
    }

    /** @return list<int> */
    private function requestedRoles(Request $request): array
    {
        $roles = $request->input('roles', []);

        return is_array($roles) ? array_values(array_map('intval', array_filter($roles, 'is_numeric'))) : [];
    }

    /** Re-throw service-level validation errors together with the submitted input. */
    private function withOldInput(Request $request, callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
    }

    /** @return array<string, mixed> */
    private function findOr404(int $id): array
    {
        return $this->users->find($id) ?? throw new HttpException(404);
    }
}
