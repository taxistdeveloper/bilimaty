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
use App\Repositories\OptionsRepository;
use App\Repositories\StudentRepository;
use App\Services\AccessScope;
use App\Services\StudentService;

/**
 * Students. View: students.view; changes: students.manage. Both are limited to the
 * groups in AccessScope, so a curator or department head cannot open other students
 * by changing the id in the URL.
 */
final class StudentController extends Controller
{
    private const STATUSES = ['active', 'academic_leave', 'expelled', 'graduated', 'transferred'];

    private StudentRepository $students;

    public function __construct()
    {
        $this->students = new StudentRepository();
    }

    public function index(Request $request): Response
    {
        $int = static fn (string $key): ?int => ctype_digit((string) $request->query($key, '')) ? (int) $request->query($key) : null;
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'group' => $int('group'),
            'course' => $int('course'),
            'status' => in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : '',
        ];
        $options = new OptionsRepository();

        return $this->view('students/index', [
            'title' => __('students.title'),
            'filters' => $filters,
            'groups' => $options->groups(false),
            'result' => $this->students->paginate($filters + ['search_iin' => Auth::can('students.manage')], max(1, (int) $request->query('page', 1))),
            'tempPassword' => Session::pullFlash('temp_password'),
        ]);
    }

    public function show(int $id): Response
    {
        $student = $this->findVisible($id);
        $canManage = $this->canManage($student);

        return $this->view('students/show', [
            'title' => full_name($student),
            'student' => $student,
            'history' => $this->students->history($id),
            'canManage' => $canManage,
            'groups' => $canManage ? (new OptionsRepository())->groups() : [],
            'statuses' => self::STATUSES,
            'guardians' => (new \App\Repositories\GuardianRepository())->guardiansOf($id),
            'tempPassword' => $canManage ? Session::pullFlash('temp_password') : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $groupId = ctype_digit((string) $request->query('group', '')) ? (int) $request->query('group') : null;

        return $this->form(null, ['group_id' => $groupId, 'enrollment_date' => date('Y-m-d'), 'locale' => 'kk']);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, $this->rules(null) + [
            'group_id' => 'required|integer|exists:groups,id',
            'order_number' => 'nullable|string|max:50',
        ]);
        $this->ensureGroupAllowed((int) $data['group_id'], $request);

        $result = (new StudentService())->enroll($data);
        Session::flash('success', __('students.enrolled'));
        Session::flash('temp_password', ['login' => $result['login'], 'password' => $result['password']]);

        return $this->redirect('/students?group=' . (int) $data['group_id']);
    }

    public function edit(int $id): Response
    {
        $student = $this->findVisible($id);
        if (!$this->canManage($student)) {
            throw new HttpException(403);
        }

        return $this->form($student, $student);
    }

    public function update(Request $request, int $id): Response
    {
        $student = $this->findVisible($id);
        if (!$this->canManage($student)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, $this->rules($student));

        (new StudentService())->update($student, $data);
        Session::flash('success', __('students.updated'));

        return $this->redirect('/students/' . $id);
    }

    public function transfer(Request $request, int $id): Response
    {
        $student = $this->findVisible($id);
        if (!$this->canManage($student)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'group_id' => 'required|integer|exists:groups,id',
            'date' => 'required|date',
            'reason' => 'required|in:transfer,promotion',
            'order_number' => 'nullable|string|max:50',
            'comment' => 'nullable|string|max:500',
        ]);
        $this->ensureGroupAllowed((int) $data['group_id'], $request);

        $this->withInput($request, fn () => (new StudentService())->transfer($student, [
            'group_id' => (int) $data['group_id'],
            'date' => (string) $data['date'],
            'reason' => (string) $data['reason'],
            'order_number' => $data['order_number'] ?? null,
            'comment' => $data['comment'] ?? null,
        ]));
        Session::flash('success', __('students.transferred'));

        return $this->redirect('/students/' . $id);
    }

    public function changeStatus(Request $request, int $id): Response
    {
        $student = $this->findVisible($id);
        if (!$this->canManage($student)) {
            throw new HttpException(403);
        }
        $data = $this->validate($request, [
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'date' => 'required|date',
            'group_id' => 'nullable|integer|exists:groups,id',
            'order_number' => 'nullable|string|max:50',
            'comment' => 'nullable|string|max:500',
        ]);
        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
        if ($data['status'] === 'active' && $groupId !== null) {
            $this->ensureGroupAllowed($groupId, $request);
        }

        $this->withInput($request, fn () => (new StudentService())->changeStatus($student, [
            'status' => (string) $data['status'],
            'date' => (string) $data['date'],
            'group_id' => $data['status'] === 'active' ? $groupId : null,
            'order_number' => $data['order_number'] ?? null,
            'comment' => $data['comment'] ?? null,
        ]));
        Session::flash('success', __('students.status_changed'));

        return $this->redirect('/students/' . $id);
    }

    /**
     * @param array<string, mixed>|null $student
     * @param array<string, mixed> $values
     */
    private function form(?array $student, array $values): Response
    {
        return $this->view('students/form', [
            'title' => $student === null ? __('students.create') : __('students.edit'),
            'student' => $student,
            'values' => $values,
            'groups' => (new OptionsRepository())->groups(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $student
     * @return array<string, string>
     */
    private function rules(?array $student): array
    {
        return \App\Validators\StudentValidator::rules($student);
    }

    /** @return array<string, mixed> */
    private function findVisible(int $id): array
    {
        $student = $this->students->find($id) ?? throw new HttpException(404);
        $groupId = $student['current_group_id'] !== null ? (int) $student['current_group_id'] : null;
        if (!AccessScope::allowsGroup($groupId)) {
            throw new HttpException(403);
        }

        return $student;
    }

    /** @param array<string, mixed> $student */
    private function canManage(array $student): bool
    {
        $groupId = $student['current_group_id'] !== null ? (int) $student['current_group_id'] : null;

        return Auth::can('students.manage') && AccessScope::allowsGroup($groupId);
    }

    private function ensureGroupAllowed(int $groupId, Request $request): void
    {
        if (!AccessScope::allowsGroup($groupId) || !$this->students->groupIsActive($groupId)) {
            throw new ValidationException(['group_id' => [__('students.group_not_allowed')]], $request->all());
        }
    }

    private function withInput(Request $request, callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            throw new ValidationException($e->errors, $request->all());
        }
    }
}
