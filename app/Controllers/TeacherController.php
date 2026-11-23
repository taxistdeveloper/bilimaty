<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OptionsRepository;
use App\Repositories\TeacherRepository;
use App\Services\AccessScope;
use App\Services\TeacherService;

/**
 * Teachers. View: teachers.view within the department scope; changes: teachers.manage.
 */
final class TeacherController extends Controller
{
    private TeacherRepository $teachers;

    public function __construct()
    {
        $this->teachers = new TeacherRepository();
    }

    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'department' => ctype_digit((string) $request->query('department', '')) ? (int) $request->query('department') : null,
            'status' => in_array($request->query('status'), ['active', 'on_leave', 'dismissed'], true) ? (string) $request->query('status') : '',
        ];

        return $this->view('teachers/index', [
            'title' => __('teachers.title'),
            'filters' => $filters,
            'departments' => (new OptionsRepository())->departments(true),
            'result' => $this->teachers->paginate($filters, max(1, (int) $request->query('page', 1))),
            'tempPassword' => Session::pullFlash('temp_password'),
        ]);
    }

    public function show(int $id): Response
    {
        $teacher = $this->findVisible($id);

        return $this->view('teachers/show', [
            'title' => full_name($teacher),
            'teacher' => $teacher,
            'groups' => $this->teachers->curatedGroups($id),
            'departments' => $this->teachers->headedDepartments($id),
            'canManage' => $this->canManage($teacher),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, $this->rules(null));
        $this->ensureDepartmentAllowed($data['department_id'] ?? null);

        $result = (new TeacherService())->create($data);
        Session::flash('success', __('teachers.created'));
        Session::flash('temp_password', ['login' => $result['login'], 'password' => $result['password']]);

        return $this->redirect('/teachers');
    }

    public function edit(int $id): Response
    {
        $teacher = $this->findVisible($id);
        if (!$this->canManage($teacher)) {
            throw new HttpException(403);
        }

        return $this->form($teacher);
    }

    public function update(Request $request, int $id): Response
    {
        $teacher = $this->findVisible($id);
        if (!$this->canManage($teacher)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, $this->rules($teacher));
        $this->ensureDepartmentAllowed($data['department_id'] ?? null);

        (new TeacherService())->update($teacher, $data);
        Session::flash('success', __('teachers.updated'));

        return $this->redirect('/teachers/' . $id);
    }

    /** @param array<string, mixed>|null $teacher */
    private function form(?array $teacher): Response
    {
        return $this->view('teachers/form', [
            'title' => $teacher === null ? __('teachers.create') : __('teachers.edit'),
            'teacher' => $teacher,
            'departments' => (new OptionsRepository())->departments(true, true),
        ]);
    }

    /**
     * @param array<string, mixed>|null $teacher
     * @return array<string, string>
     */
    private function rules(?array $teacher): array
    {
        return \App\Validators\TeacherValidator::rules($teacher);
    }

    /** @return array<string, mixed> */
    private function findVisible(int $id): array
    {
        $teacher = $this->teachers->find($id) ?? throw new HttpException(404);
        // Own teacher card is always visible; otherwise the department must be in scope.
        $isSelf = (int) $teacher['user_id'] === Auth::id();
        if (!$isSelf && !AccessScope::allowsDepartment($teacher['department_id'] !== null ? (int) $teacher['department_id'] : null)) {
            throw new HttpException(403);
        }

        return $teacher;
    }

    /** @param array<string, mixed> $teacher */
    private function canManage(array $teacher): bool
    {
        return Auth::can('teachers.manage')
            && AccessScope::allowsDepartment($teacher['department_id'] !== null ? (int) $teacher['department_id'] : null);
    }

    private function ensureDepartmentAllowed(mixed $departmentId): void
    {
        if (!AccessScope::allowsDepartment($departmentId !== null ? (int) $departmentId : null)) {
            throw new HttpException(403);
        }
    }
}
