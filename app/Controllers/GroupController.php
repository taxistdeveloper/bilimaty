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
use App\Repositories\CurriculumRepository;
use App\Repositories\GroupRepository;
use App\Repositories\OptionsRepository;
use App\Services\AccessScope;
use App\Services\AuditLogger;

/**
 * Study groups. View: groups.view within AccessScope; changes: groups.manage within
 * the department scope (a department head manages the groups of their departments).
 */
final class GroupController extends Controller
{
    private const STATUSES = ['active', 'graduated', 'archived', 'disbanded'];
    private const FORMS = ['full_time', 'part_time', 'evening', 'distance'];
    private const FIELDS = ['code', 'name', 'specialty_id', 'qualification_id', 'curriculum_id', 'course', 'admission_year',
        'academic_year_id', 'curator_teacher_id', 'study_form', 'study_language', 'status'];

    private GroupRepository $groups;

    public function __construct()
    {
        $this->groups = new GroupRepository();
    }

    /** Teacher: groups taught this semester and curated groups. */
    public function mine(): Response
    {
        $teacherId = AccessScope::teacherId() ?? throw new HttpException(403);

        return $this->view('groups/mine', ['title' => __('teaching.my_groups'), 'groups' => (new \App\Services\TeachingService())->groups($teacherId)]);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $options = new OptionsRepository();

        return $this->view('groups/index', [
            'title' => __('groups.title'),
            'filters' => $filters,
            'departments' => $options->departments(true),
            'specialties' => $options->specialties(true),
            'years' => $options->academicYears(),
            'result' => $this->groups->paginate($filters, max(1, (int) $request->query('page', 1))),
        ]);
    }

    public function show(int $id): Response
    {
        $group = $this->findVisible($id);

        return $this->view('groups/show', [
            'title' => __('groups.one') . ' ' . $group['code'],
            'group' => $group,
            'students' => Auth::can('students.view') ? $this->groups->students($id) : [],
            'canManage' => $this->canManage($group),
            'usage' => $this->groups->usageCount($id),
        ]);
    }

    public function create(): Response
    {
        $options = new OptionsRepository();

        return $this->form(null, [
            'course' => 1,
            'admission_year' => (int) date('Y'),
            'academic_year_id' => $options->activeAcademicYearId(),
            'study_form' => 'full_time',
            'study_language' => 'kk',
            'status' => 'active',
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request, null);
        $id = $this->groups->create($data);
        AuditLogger::log('groups.created', 'groups', $id, null, $data);
        Session::flash('success', __('groups.created'));

        return $this->redirect('/groups/' . $id);
    }

    public function edit(int $id): Response
    {
        $group = $this->findVisible($id);
        if (!$this->canManage($group)) {
            throw new HttpException(403);
        }

        return $this->form($group, $group);
    }

    public function update(Request $request, int $id): Response
    {
        $group = $this->findVisible($id);
        if (!$this->canManage($group)) {
            throw new HttpException(403);
        }

        $data = $this->validated($request, $id);
        $this->groups->update($id, $data);

        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', array_intersect_key($group, $data))));
        if ($changed !== []) {
            $keys = array_flip($changed);
            AuditLogger::log('groups.updated', 'groups', $id, array_intersect_key($group, $keys), array_intersect_key($data, $keys));
        }
        Session::flash('success', __('groups.updated'));

        return $this->redirect('/groups/' . $id);
    }

    public function destroy(int $id): Response
    {
        $group = $this->findVisible($id);
        if (!$this->canManage($group)) {
            throw new HttpException(403);
        }
        if ($this->groups->usageCount($id) > 0) {
            Session::flash('error', __('groups.in_use'));

            return $this->redirect('/groups/' . $id);
        }

        $this->groups->delete($id);
        AuditLogger::log('groups.deleted', 'groups', $id, array_intersect_key($group, array_flip(self::FIELDS)));
        Session::flash('success', __('groups.deleted'));

        return $this->redirect('/groups');
    }

    /** GET /api/groups — groups visible to the user. */
    public function apiIndex(Request $request): Response
    {
        return $this->json($this->groups->listForApi($this->filters($request)));
    }

    /** GET /api/groups/{id}/students — minimal data, no personal identifiers. */
    public function apiStudents(int $id): Response
    {
        $this->findVisible($id);
        if (!Auth::can('students.view')) {
            throw new HttpException(403);
        }

        $students = array_map(static fn (array $s): array => [
            'id' => (int) $s['id'],
            'name' => full_name($s),
            'student_number' => $s['student_number'],
            'status' => $s['status'],
        ], $this->groups->students($id));

        return $this->json($students);
    }

    /**
     * @param array<string, mixed>|null $group
     * @param array<string, mixed> $values
     */
    private function form(?array $group, array $values): Response
    {
        $options = new OptionsRepository();

        return $this->view('groups/form', [
            'title' => $group === null ? __('groups.create') : __('groups.edit'),
            'group' => $group,
            'values' => $values,
            'specialties' => $options->specialties(true, true),
            'qualifications' => $options->qualifications(),
            'curricula' => (new CurriculumRepository())->optionsForSpecialty(),
            'years' => $options->academicYears(),
            'teachers' => $options->teachers(),
            'statuses' => self::STATUSES,
            'forms' => self::FORMS,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id): array
    {
        $ignore = $id !== null ? ',' . $id : '';
        $data = $this->validate($request, [
            'code' => 'required|string|max:30|unique:groups,code' . $ignore,
            'name' => 'required|string|max:100',
            'specialty_id' => 'required|integer|exists:specialties,id',
            'qualification_id' => 'nullable|integer|exists:qualifications,id',
            'curriculum_id' => 'nullable|integer|exists:curricula,id',
            'course' => 'required|integer|between:1,4',
            'admission_year' => 'required|integer|between:2000,2100',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'curator_teacher_id' => 'nullable|integer|exists:teachers,id',
            'study_form' => 'required|in:' . implode(',', self::FORMS),
            'study_language' => 'required|in:kk,ru',
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        foreach (['specialty_id', 'qualification_id', 'curriculum_id', 'course', 'admission_year', 'academic_year_id', 'curator_teacher_id'] as $key) {
            $data[$key] = isset($data[$key]) ? (int) $data[$key] : null;
        }

        if ($data['qualification_id'] !== null && !$this->groups->qualificationBelongs($data['qualification_id'], $data['specialty_id'])) {
            throw new ValidationException(['qualification_id' => [__('groups.qualification_mismatch')]], $request->all());
        }
        if ($data['curriculum_id'] !== null) {
            $plan = (new CurriculumRepository())->optionsForSpecialty($data['specialty_id'])[$data['curriculum_id']] ?? null;
            if ($plan === null) {
                throw new ValidationException(['curriculum_id' => [__('groups.curriculum_mismatch')]], $request->all());
            }
        }
        // A department head may only place groups into their own departments.
        if (!AccessScope::allowsDepartment($this->groups->departmentOfSpecialty($data['specialty_id']))) {
            throw new ValidationException(['specialty_id' => [__('groups.specialty_out_of_scope')]], $request->all());
        }

        return array_intersect_key($data, array_flip(self::FIELDS));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $int = static fn (string $key): ?int => ctype_digit((string) $request->query($key, '')) ? (int) $request->query($key) : null;

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'department' => $int('department'),
            'specialty' => $int('specialty'),
            'course' => $int('course'),
            'year' => $int('year'),
            'status' => in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : '',
        ];
    }

    /** @return array<string, mixed> */
    private function findVisible(int $id): array
    {
        $group = $this->groups->find($id) ?? throw new HttpException(404);
        if (!AccessScope::allowsGroup($id)) {
            throw new HttpException(403);
        }

        return $group;
    }

    /** @param array<string, mixed> $group */
    private function canManage(array $group): bool
    {
        return Auth::can('groups.manage') && AccessScope::allowsDepartment((int) $group['department_id']);
    }
}
