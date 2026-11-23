<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Policies\RolePolicy;
use App\Repositories\RoleRepository;
use App\Services\AuditLogger;

final class RoleController extends Controller
{
    private RoleRepository $roles;
    private RolePolicy $policy;

    public function __construct()
    {
        $this->roles = new RoleRepository();
        $this->policy = new RolePolicy();
    }

    public function index(): Response
    {
        return $this->view('roles/index', [
            'title' => __('roles.title'),
            'roles' => $this->roles->allWithCounts(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'slug' => 'required|alpha_dash|min:3|max:50|unique:roles,slug',
            'name_ru' => 'required|string|max:150',
            'name_kk' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
        ]);
        $data['slug'] = mb_strtolower((string) $data['slug']);

        $id = $this->roles->create($data);
        AuditLogger::log('roles.created', 'roles', $id, null, $data);
        Session::flash('success', __('roles.created'));

        return $this->redirect('/roles/' . $id . '/edit');
    }

    public function edit(int $id): Response
    {
        $role = $this->findOr404($id);
        $permissions = $this->roles->permissions();

        $modules = [];
        foreach ($permissions as $permission) {
            $modules[$permission['module']][] = $permission;
        }

        return $this->view('roles/edit', [
            'title' => __('roles.edit') . ': ' . localized($role),
            'role' => $role,
            'modules' => $modules,
            'granted' => $this->roles->permissionIds($id),
            'grantable' => $this->policy->grantablePermissions(array_column($permissions, 'slug')),
            'canEdit' => $this->policy->editPermissions($role),
            'canDelete' => $this->policy->delete($role, $this->roles->usersCount($id)),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $role = $this->findOr404($id);
        if (!$this->policy->editPermissions($role)) {
            throw new HttpException(403);
        }

        $data = $this->validate($request, [
            'name_ru' => 'required|string|max:150',
            'name_kk' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array|exists:permissions,id',
        ]);

        $all = $this->roles->permissions();
        $idBySlug = array_column($all, 'id', 'slug');
        $grantableIds = array_map(
            static fn (string $slug): int => (int) $idBySlug[$slug],
            $this->policy->grantablePermissions(array_keys($idBySlug))
        );

        $current = $this->roles->permissionIds($id);
        $requested = array_map('intval', (array) ($data['permissions'] ?? []));
        // Only permissions the actor holds can be toggled; the rest keep their state.
        $final = array_values(array_unique(array_merge(
            array_intersect($requested, $grantableIds),
            array_diff($current, $grantableIds)
        )));
        sort($final);

        Database::transaction(function () use ($id, $data, $final): void {
            $this->roles->updateNames($id, [
                'name_ru' => $data['name_ru'],
                'name_kk' => $data['name_kk'],
                'description' => $data['description'] ?? null,
            ]);
            $this->roles->syncPermissions($id, $final);
        });

        $slugById = array_flip($idBySlug);
        $toSlugs = static fn (array $ids): array => array_values(array_map(static fn (int $i): string => (string) $slugById[$i], $ids));
        $added = array_diff($final, $current);
        $removed = array_diff($current, $final);
        AuditLogger::log('roles.updated', 'roles', $id,
            ['name_ru' => $role['name_ru'], 'name_kk' => $role['name_kk'], 'revoked' => $toSlugs($removed)],
            ['name_ru' => $data['name_ru'], 'name_kk' => $data['name_kk'], 'granted' => $toSlugs($added)]
        );
        Session::flash('success', __('roles.updated'));

        return $this->redirect('/roles/' . $id . '/edit');
    }

    public function destroy(int $id): Response
    {
        $role = $this->findOr404($id);
        if (!$this->policy->delete($role, $this->roles->usersCount($id))) {
            throw new HttpException(403);
        }

        $this->roles->delete($id);
        AuditLogger::log('roles.deleted', 'roles', $id, ['slug' => $role['slug'], 'name_ru' => $role['name_ru']]);
        Session::flash('success', __('roles.deleted'));

        return $this->redirect('/roles');
    }

    /** @return array<string, mixed> */
    private function findOr404(int $id): array
    {
        return $this->roles->find($id) ?? throw new HttpException(404);
    }
}
